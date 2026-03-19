#!/usr/bin/env python3
"""
bmecat2catalog.py — Transforms BMECAT 2005 XML into offline catalog JSON data.

Usage:
    python3 bmecat2catalog.py input.xml -o output_dir/
    python3 bmecat2catalog.py input.xml -o output_dir/ --images-dir /path/to/images
    python3 bmecat2catalog.py input.xml -o output_dir/ --chunk-size 200
    python3 bmecat2catalog.py input.xml -o output_dir/ --catalog-name "My Catalog"

The script produces:
    output_dir/
      data/
        products/
          index.json
          chunk-0.json
          chunk-1.json
          ...
        products-detail/
          0/
            {product-id}.json
            ...  (max 1000 per subdirectory)
          1/
            ...
        categories.json
        facets.json
        settings.json
"""

import argparse
import hashlib
import json
import os
import re
import shutil
import sys
import uuid
import xml.etree.ElementTree as ET
from collections import defaultdict


NS = {"bme": "http://www.bmecat.org/bmecat/2005"}


def deterministic_uuid(seed: str) -> str:
    """Generate a deterministic UUID v5 from a seed string."""
    return str(uuid.uuid5(uuid.NAMESPACE_URL, f"bmecat:{seed}"))


def parse_bmecat(xml_path: str) -> dict:
    """Parse a BMECAT 2005 XML file and return header + products."""
    tree = ET.parse(xml_path)
    root = tree.getroot()

    header_el = root.find("bme:HEADER", NS)
    catalog_el = header_el.find("bme:CATALOG", NS) if header_el is not None else None
    supplier_el = header_el.find("bme:SUPPLIER", NS) if header_el is not None else None

    header = {
        "language": _text(catalog_el, "bme:LANGUAGE"),
        "catalog_id": _text(catalog_el, "bme:CATALOG_ID"),
        "catalog_version": _text(catalog_el, "bme:CATALOG_VERSION"),
        "catalog_name": _text(catalog_el, "bme:CATALOG_NAME"),
        "currency": _text(catalog_el, "bme:CURRENCY") or "EUR",
        "supplier_name": _text(supplier_el, "bme:SUPPLIER_NAME"),
    }

    products = []
    for section in ["bme:T_NEW_CATALOG", "bme:T_UPDATE_PRODUCTS", "bme:T_UPDATE_PRICES"]:
        container = root.find(section, NS)
        if container is None:
            continue
        for prod_el in container.findall("bme:PRODUCT", NS):
            products.append(_parse_product(prod_el, header["currency"]))

    return {"header": header, "products": products}


def _text(parent, path: str) -> str | None:
    """Get text content of a child element, or None."""
    if parent is None:
        return None
    el = parent.find(path, NS)
    return el.text.strip() if el is not None and el.text else None


def _parse_product(prod_el, default_currency: str) -> dict:
    """Parse a single PRODUCT element."""
    sku = _text(prod_el, "bme:SUPPLIER_PID") or ""
    product_id = deterministic_uuid(sku)

    details = prod_el.find("bme:PRODUCT_DETAILS", NS)
    name = _text(details, "bme:DESCRIPTION_SHORT") or sku
    description = _text(details, "bme:DESCRIPTION_LONG") or ""
    ean = _text(details, "bme:EAN") or ""

    # Features
    features = []
    features_el = prod_el.find("bme:PRODUCT_FEATURES", NS)
    if features_el is not None:
        for feat in features_el.findall("bme:FEATURE", NS):
            fname = _text(feat, "bme:FNAME") or ""
            fvalue = _text(feat, "bme:FVALUE")
            funit = _text(feat, "bme:FUNIT")
            forder_text = _text(feat, "bme:FORDER")
            forder = int(forder_text) if forder_text else 999
            if fname and fvalue is not None:
                features.append({
                    "name": fname,
                    "value": fvalue,
                    "unit": funit,
                    "order": forder,
                })

    # Prices
    prices = []
    price_details = prod_el.find("bme:PRODUCT_PRICE_DETAILS", NS)
    if price_details is not None:
        for pp in price_details.findall("bme:PRODUCT_PRICE", NS):
            price_type = pp.get("price_type", "net_list")
            amount_str = _text(pp, "bme:PRICE_AMOUNT")
            currency = _text(pp, "bme:PRICE_CURRENCY") or default_currency
            tax_str = _text(pp, "bme:TAX")
            if amount_str:
                prices.append({
                    "amount": float(amount_str),
                    "currency": currency,
                    "price_type": _normalize_price_type(price_type),
                    "tax": float(tax_str) if tax_str else None,
                })

    # Media / images
    media = []
    mime_info = prod_el.find("bme:MIME_INFO", NS)
    if mime_info is not None:
        for mime in mime_info.findall("bme:MIME", NS):
            mime_type = _text(mime, "bme:MIME_TYPE") or "image/jpeg"
            mime_source = _text(mime, "bme:MIME_SOURCE") or ""
            mime_purpose = _text(mime, "bme:MIME_PURPOSE") or "normal"
            mime_order = _text(mime, "bme:MIME_ORDER")
            if mime_source:
                media.append({
                    "id": deterministic_uuid(f"{sku}:media:{mime_source}"),
                    "file_name": mime_source,
                    "mime_type": mime_type,
                    "url": f"media/{mime_source}",
                    "purpose": mime_purpose,
                    "order": int(mime_order) if mime_order else 1,
                })

    # References (relations)
    relations = []
    for ref in prod_el.findall("bme:PRODUCT_REFERENCE", NS):
        ref_type = ref.get("type", "similar")
        target_sku = _text(ref, "bme:PROD_ID_TO")
        if target_sku and target_sku.strip():
            relations.append({
                "target_sku": target_sku.strip(),
                "target_product_id": deterministic_uuid(target_sku.strip()),
                "relation_type": _normalize_relation_type(ref_type),
            })

    return {
        "id": product_id,
        "sku": sku,
        "ean": ean,
        "name": name,
        "description": description,
        "features": sorted(features, key=lambda f: f["order"]),
        "prices": prices,
        "media": sorted(media, key=lambda m: m["order"]),
        "relations": relations,
    }


def _normalize_price_type(bmecat_type: str) -> str:
    mapping = {
        "net_list": "Listenpreis",
        "list_price": "Listenpreis",
        "net_customer": "Kundenpreis",
        "nrp": "UVP",
        "udp": "UVP",
    }
    return mapping.get(bmecat_type, bmecat_type)


def _normalize_relation_type(bmecat_type: str) -> str:
    mapping = {
        "accessory": "Zubehoer",
        "accessory_for": "Zubehoer fuer",
        "similar": "Aehnlich",
        "followup": "Nachfolger",
        "mandatory": "Pflicht",
        "select": "Auswahl",
        "diff_orderunit": "Andere Bestelleinheit",
        "consists_of": "Besteht aus",
        "sparepart": "Ersatzteil",
    }
    return mapping.get(bmecat_type, bmecat_type)


# ─── Transform to offline catalog format ─────────────────────────────────────

DETAIL_DIR_SIZE = 1000  # max JSON files per detail subdirectory


def build_catalog_data(parsed: dict, chunk_size: int, catalog_name: str | None,
                       primary_color: str, images_dir: str | None) -> dict:
    """Transform parsed BMECAT data into the offline catalog directory structure."""
    header = parsed["header"]
    products = parsed["products"]

    # Build SKU lookup for relation name resolution
    sku_lookup = {p["sku"]: p for p in products}

    # Build product list items (for chunks)
    product_list = []
    product_details = {}
    all_features = defaultdict(lambda: defaultdict(int))  # name -> value -> count
    numeric_features = defaultdict(list)  # name -> [values]

    for product_counter, p in enumerate(products):
        # Determine primary image
        image_url = None
        if p["media"]:
            image_url = p["media"][0]["url"]

        # Primary price
        price = p["prices"][0]["amount"] if p["prices"] else None
        currency = p["prices"][0]["currency"] if p["prices"] else header["currency"]

        # Card attributes: first 4 non-Kurzbeschreibung features
        card_attrs = []
        for feat in p["features"]:
            if feat["name"] == "Kurzbeschreibung":
                continue
            attr_id = deterministic_uuid(f"attr:{feat['name']}")
            display_value = feat["value"]
            if feat["unit"]:
                display_value = f"{feat['value']} {feat['unit']}"
            card_attrs.append({
                "attribute_id": attr_id,
                "label": feat["name"],
                "value": display_value,
            })
            if len(card_attrs) >= 4:
                break

        # Facet values (compact format) + collect for facets.json
        facet_values = {}
        for feat in p["features"]:
            if feat["name"] == "Kurzbeschreibung":
                continue
            attr_id = deterministic_uuid(f"attr:{feat['name']}")
            # Try to detect numeric values
            try:
                num_val = float(feat["value"])
                facet_values[attr_id] = num_val
                numeric_features[feat["name"]].append(num_val)
            except (ValueError, TypeError):
                if feat["value"].lower() in ("true", "false"):
                    bool_val = feat["value"].lower() == "true"
                    facet_values[attr_id] = bool_val
                    all_features[feat["name"]][str(bool_val)] += 1
                else:
                    value_id = deterministic_uuid(f"val:{feat['name']}:{feat['value']}")
                    facet_values[attr_id] = value_id
                    all_features[feat["name"]][feat["value"]] += 1

        # Searchable text
        searchable_parts = [p["name"], p["sku"], p["ean"], p["description"]]
        for feat in p["features"]:
            searchable_parts.append(feat["value"])
        searchable_text = " ".join(filter(None, searchable_parts)).lower()

        detail_bucket = product_counter // DETAIL_DIR_SIZE

        # Compact chunk format — only essential fields, optional fields omitted if empty
        item = {
            "id": p["id"],
            "sku": p["sku"],
            "name": p["name"],
            "cat": None,
            "cats": [],
            "img": image_url,
            "_dd": detail_bucket,
        }
        if p["ean"]:
            item["ean"] = p["ean"]
        if price is not None:
            item["price"] = price
        if currency != "EUR":
            item["cur"] = currency
        if card_attrs:
            item["attrs"] = card_attrs
        if searchable_text:
            item["search"] = searchable_text[:200]
        if facet_values:
            item["facets"] = facet_values

        product_list.append(item)

        # Full detail
        attrs_detail = []
        for feat in p["features"]:
            if feat["name"] == "Kurzbeschreibung":
                continue
            attr_id = deterministic_uuid(f"attr:{feat['name']}")
            display_value = feat["value"]
            if feat["unit"]:
                display_value = f"{feat['value']} {feat['unit']}"
            data_type = _detect_data_type(feat["value"])
            attrs_detail.append({
                "attribute_id": attr_id,
                "technical_name": _slugify(feat["name"]),
                "label": feat["name"],
                "data_type": data_type,
                "value": display_value,
                "raw_value": feat["value"],
                "unit": feat["unit"],
                "group": None,
            })

        resolved_relations = []
        for rel in p["relations"]:
            target = sku_lookup.get(rel["target_sku"])
            resolved_relations.append({
                "target_product_id": rel["target_product_id"],
                "sku": rel["target_sku"],
                "name": target["name"] if target else rel["target_sku"],
                "relation_type": rel["relation_type"],
                "relation_type_id": deterministic_uuid(f"reltype:{rel['relation_type']}"),
            })

        detail_prices = []
        for pr in p["prices"]:
            detail_prices.append({
                "amount": pr["amount"],
                "currency": pr["currency"],
                "price_type": pr["price_type"],
            })

        detail_media = []
        for m in p["media"]:
            detail_media.append({
                "id": m["id"],
                "file_name": m["file_name"],
                "mime_type": m["mime_type"],
                "url": m["url"],
            })

        product_details[p["id"]] = {
            "id": p["id"],
            "sku": p["sku"],
            "ean": p["ean"],
            "name": p["name"],
            "description": p["description"],
            "breadcrumb": [],
            "attributes": attrs_detail,
            "description_attributes": [],
            "media": detail_media,
            "prices": detail_prices,
            "relations": resolved_relations,
            "variants": [],
        }

    # Build chunks
    chunks = []
    for i in range(0, len(product_list), chunk_size):
        chunk_name = f"chunk-{len(chunks)}.json"
        chunks.append({
            "name": chunk_name,
            "data": product_list[i : i + chunk_size],
        })

    total_detail_buckets = (len(product_list) - 1) // DETAIL_DIR_SIZE + 1 if product_list else 0
    index = {
        "totalProducts": len(product_list),
        "chunkSize": chunk_size,
        "chunks": [c["name"] for c in chunks],
        "detailDirSize": DETAIL_DIR_SIZE,
        "detailBuckets": total_detail_buckets,
    }

    # Build facets
    facets = _build_facets(all_features, numeric_features)

    # Categories (empty — BMECAT doesn't have hierarchy, can be enriched later)
    categories = {
        "data": {
            "hierarchy_id": deterministic_uuid("hierarchy:bmecat"),
            "hierarchy_name": catalog_name or header["catalog_name"] or "Katalog",
            "type": "master",
            "nodes": [],
        }
    }

    # Settings
    settings = {
        "data": {
            "catalog_name": catalog_name or header["catalog_name"] or "Katalog",
            "primary_color": primary_color,
            "card_show_sku": True,
            "card_show_category": False,
            "card_show_price": True,
            "catalog_compare_enabled": True,
            "catalog_compare_max_products": 4,
            "catalog_share_wishlist_enabled": False,
            "catalog_pdf_enabled": False,
            "catalog_excel_export_enabled": False,
            "catalog_access_mode": "public",
            "mode": "offline",
        }
    }

    return {
        "index": index,
        "chunks": chunks,
        "product_details": product_details,
        "categories": categories,
        "facets": facets,
        "settings": settings,
    }


def _build_facets(all_features: dict, numeric_features: dict) -> dict:
    """Build facets.json from collected feature data."""
    facets = []

    # Categorical / boolean facets
    for fname, value_counts in sorted(all_features.items()):
        attr_id = deterministic_uuid(f"attr:{fname}")
        # Check if it's a boolean facet
        keys = set(value_counts.keys())
        if keys <= {"True", "False"}:
            values = []
            if "True" in value_counts:
                values.append({"value": "Ja", "filter_value": "1", "count": value_counts["True"]})
            if "False" in value_counts:
                values.append({"value": "Nein", "filter_value": "0", "count": value_counts["False"]})
            facets.append({
                "attribute_id": attr_id,
                "label": fname,
                "data_type": "Boolean",
                "values": values,
            })
        else:
            values = []
            for val, count in sorted(value_counts.items()):
                values.append({
                    "value": val,
                    "value_id": deterministic_uuid(f"val:{fname}:{val}"),
                    "count": count,
                })
            facets.append({
                "attribute_id": attr_id,
                "label": fname,
                "data_type": "ValueList",
                "values": values,
            })

    # Numeric facets
    for fname, values in sorted(numeric_features.items()):
        if fname in all_features:
            continue  # Already handled as categorical
        attr_id = deterministic_uuid(f"attr:{fname}")
        facets.append({
            "attribute_id": attr_id,
            "label": fname,
            "data_type": "Decimal",
            "min": min(values),
            "max": max(values),
            "count": len(values),
        })

    return {"facets": facets}


def _detect_data_type(value: str) -> str:
    if value.lower() in ("true", "false"):
        return "Flag"
    try:
        float(value)
        return "Decimal" if "." in value else "Integer"
    except (ValueError, TypeError):
        return "String"


def _slugify(name: str) -> str:
    s = name.lower()
    s = re.sub(r"[äÄ]", "ae", s)
    s = re.sub(r"[öÖ]", "oe", s)
    s = re.sub(r"[üÜ]", "ue", s)
    s = re.sub(r"ß", "ss", s)
    s = re.sub(r"[^a-z0-9]+", "_", s)
    return s.strip("_")


# ─── Write output ─────────────────────────────────────────────────────────────

def write_output(catalog_data: dict, output_dir: str, images_dir: str | None):
    """Write all JSON files to the output directory."""
    data_dir = os.path.join(output_dir, "data")
    products_dir = os.path.join(data_dir, "products")
    detail_dir = os.path.join(data_dir, "products-detail")

    os.makedirs(products_dir, exist_ok=True)
    os.makedirs(detail_dir, exist_ok=True)

    # products/index.json
    _write_json(os.path.join(products_dir, "index.json"), catalog_data["index"])

    # products/chunk-*.json
    for chunk in catalog_data["chunks"]:
        _write_json(os.path.join(products_dir, chunk["name"]), chunk["data"])

    # products-detail/{bucket}/{id}.json — max DETAIL_DIR_SIZE files per subdirectory
    product_list = []
    for chunk in catalog_data["chunks"]:
        product_list.extend(chunk["data"])

    for counter, item in enumerate(product_list):
        pid = item["id"]
        if pid not in catalog_data["product_details"]:
            continue
        bucket = counter // DETAIL_DIR_SIZE
        bucket_dir = os.path.join(detail_dir, str(bucket))
        os.makedirs(bucket_dir, exist_ok=True)
        _write_json(os.path.join(bucket_dir, f"{pid}.json"), catalog_data["product_details"][pid])

    # categories.json
    _write_json(os.path.join(data_dir, "categories.json"), catalog_data["categories"])

    # facets.json
    _write_json(os.path.join(data_dir, "facets.json"), catalog_data["facets"])

    # settings.json
    _write_json(os.path.join(data_dir, "settings.json"), catalog_data["settings"])

    # Copy images if provided
    if images_dir and os.path.isdir(images_dir):
        media_dir = os.path.join(output_dir, "media")
        os.makedirs(media_dir, exist_ok=True)
        for detail in catalog_data["product_details"].values():
            for m in detail.get("media", []):
                src = os.path.join(images_dir, m["file_name"])
                if os.path.isfile(src):
                    shutil.copy2(src, os.path.join(media_dir, m["file_name"]))

    total = catalog_data["index"]["totalProducts"]
    chunks = len(catalog_data["chunks"])
    facets = len(catalog_data["facets"]["facets"])
    print(f"[OK] {total} Produkte in {chunks} Chunk(s), {facets} Facetten")
    print(f"[OK] Output: {output_dir}")


def _write_json(path: str, data):
    with open(path, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)


# ─── CLI ──────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(
        description="BMECAT 2005 XML → Offline-Katalog JSON Transformer"
    )
    parser.add_argument("input", help="BMECAT XML Datei")
    parser.add_argument("-o", "--output", default="catalog-data",
                        help="Ausgabeverzeichnis (default: catalog-data)")
    parser.add_argument("--chunk-size", type=int, default=500,
                        help="Produkte pro Chunk (default: 500)")
    parser.add_argument("--catalog-name", help="Katalogname (ueberschreibt BMECAT Header)")
    parser.add_argument("--primary-color", default="#2563eb",
                        help="Primaerfarbe (default: #2563eb)")
    parser.add_argument("--images-dir",
                        help="Verzeichnis mit Produktbildern (werden nach media/ kopiert)")

    args = parser.parse_args()

    if not os.path.isfile(args.input):
        print(f"[FEHLER] Datei nicht gefunden: {args.input}", file=sys.stderr)
        sys.exit(1)

    print(f"[...] Parse {args.input}")
    parsed = parse_bmecat(args.input)
    print(f"[OK] {len(parsed['products'])} Produkte aus BMECAT gelesen")
    print(f"     Katalog: {parsed['header']['catalog_name']}")
    print(f"     Lieferant: {parsed['header']['supplier_name']}")

    print(f"[...] Transformiere in Offline-Katalog Format")
    catalog_data = build_catalog_data(
        parsed,
        chunk_size=args.chunk_size,
        catalog_name=args.catalog_name,
        primary_color=args.primary_color,
        images_dir=args.images_dir,
    )

    print(f"[...] Schreibe Ausgabe nach {args.output}")
    write_output(catalog_data, args.output, args.images_dir)


if __name__ == "__main__":
    main()
