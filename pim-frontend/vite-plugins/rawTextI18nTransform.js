import { NodeTypes } from '@vue/compiler-core';

/**
 * PoC: Vue-SFC-Compiler-Transform, das hartkodierte Template-Texte automatisch
 * in $t()-Aufrufe umschreibt — ohne die .vue-Dateien selbst anzufassen.
 *
 * Der Original-Text wird 1:1 als vue-i18n-Key verwendet (kein erfundener Key).
 * Uebersetzungen liegen in src/locales/productEditorRawText.js.
 *
 * Scope aktuell: nur Produkteditor (siehe SCOPE_PATTERNS), zum Testen des Ansatzes
 * bevor er auf weitere Module ausgeweitet wird.
 */

const SCOPE_PATTERNS = [
  /[/\\]views[/\\]products[/\\]ProductDetailView\.vue$/,
  /[/\\]views[/\\]products[/\\]ProductListView\.vue$/,
  /[/\\]components[/\\]products[/\\]/,
  /[/\\]views[/\\]dashboard[/\\]/,
  /[/\\]components[/\\]dashboard[/\\]/,
];

// Statische Attribute, die als sichtbarer UI-Text gelten (nicht z.B. "class", "id", "type").
const TRANSLATABLE_ATTRIBUTES = new Set(['title', 'placeholder', 'alt', 'aria-label', 'aria-placeholder', 'empty-text']);

function isInScope(filename) {
  if (!filename) return false;
  return SCOPE_PATTERNS.some((pattern) => pattern.test(filename));
}

// Symbole, Zahlen, Icons (z.B. "—", "*", "500", "px"-lose Zeichen) sind keine
// Uebersetzungs-Kandidaten. Mindestens ein Buchstabe muss enthalten sein.
function isTranslatableText(raw) {
  const trimmed = raw.trim();
  return trimmed.length > 0 && /\p{L}/u.test(trimmed);
}

function simpleExpression(content, isStatic, loc) {
  return {
    type: NodeTypes.SIMPLE_EXPRESSION,
    content,
    isStatic,
    constType: isStatic ? 3 : 0,
    loc,
  };
}

function wrapTextNode(node) {
  const raw = node.content;
  const trimmed = raw.trim();
  const start = raw.indexOf(trimmed);
  const leading = raw.slice(0, start);
  const trailing = raw.slice(start + trimmed.length);

  // Fuehrende/folgende Whitespaces bleiben erhalten, damit sich am Layout nichts aendert.
  const expr = `${JSON.stringify(leading)}+_ctx.$t(${JSON.stringify(trimmed)})+${JSON.stringify(trailing)}`;

  node.type = NodeTypes.INTERPOLATION;
  node.content = simpleExpression(expr, false, node.loc);
}

function wrapAttribute(prop) {
  const raw = prop.value ? prop.value.content : '';
  const attrName = prop.name;
  const loc = prop.loc;

  return {
    type: NodeTypes.DIRECTIVE,
    name: 'bind',
    arg: simpleExpression(attrName, true, loc),
    exp: simpleExpression(`_ctx.$t(${JSON.stringify(raw)})`, false, loc),
    modifiers: [],
    loc,
  };
}

export function rawTextI18nTransform(node, context) {
  if (!isInScope(context.filename)) return;

  if (node.type === NodeTypes.TEXT) {
    if (isTranslatableText(node.content)) {
      wrapTextNode(node);
    }
    return;
  }

  if (node.type === NodeTypes.ELEMENT) {
    node.props = node.props.map((prop) => {
      if (
        prop.type === NodeTypes.ATTRIBUTE &&
        TRANSLATABLE_ATTRIBUTES.has(prop.name) &&
        prop.value &&
        isTranslatableText(prop.value.content)
      ) {
        return wrapAttribute(prop);
      }
      return prop;
    });
  }
}
