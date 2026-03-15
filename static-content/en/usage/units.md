---
title: Units
---

# Units

The Units module in anyPIM allows you to define measurement units and organize them into logical groups. Units are assigned to attributes of type **Float**, ensuring that numeric values carry meaningful context -- such as weight in kilograms or length in millimeters. The system supports conversion factors between units within the same group, enabling automatic unit conversion during data entry and export.

## Unit Groups

A unit group represents a category of related measurement units (for example, "Weight" or "Length"). Each unit group contains one **base unit** and one or more additional units with defined conversion factors.

### Viewing Unit Groups

Navigate to **Units** in the sidebar to see the list of all defined unit groups:

| Column | Description |
|---|---|
| **Name** | Display name of the unit group (e.g., "Weight") |
| **Base Unit** | The reference unit used for internal storage and conversion |
| **Units** | The number of units defined in the group |
| **Attributes** | The number of attributes linked to this unit group |

### Creating a Unit Group

Click **+ New Unit Group** to create a new group. The following fields are required:

| Field | Description | Required |
|---|---|---|
| **Name** | Display name of the unit group | Yes |
| **Description** | Optional internal description of the group's purpose | No |

After creating the group, you can add individual units to it.

## Units

Each unit within a group has a name, an abbreviation, and a conversion factor relative to the base unit.

### Adding a Unit

1. Open the unit group detail view.
2. Click **+ Add Unit**.
3. Fill in the following fields:

| Field | Description | Required |
|---|---|---|
| **Name** | Full name of the unit (e.g., "Kilogram") | Yes |
| **Abbreviation** | Short symbol (e.g., "kg") | Yes |
| **Conversion Factor** | Multiplier to convert this unit to the base unit | Yes |
| **Is Base Unit** | Whether this unit is the base unit of the group | No |

::: tip Note
Each unit group must have exactly one base unit. The base unit has a conversion factor of 1. All other units in the group define their conversion factor relative to this base unit.
:::

### Conversion Factor Explained

The conversion factor specifies how many base units one unit of the current unit represents. For example, in a "Weight" group with Kilogram as the base unit:

| Unit | Abbreviation | Conversion Factor | Interpretation |
|---|---|---|---|
| Kilogram | kg | 1 | Base unit |
| Gram | g | 0.001 | 1 g = 0.001 kg |
| Milligram | mg | 0.000001 | 1 mg = 0.000001 kg |
| Ton | t | 1000 | 1 t = 1000 kg |
| Pound | lb | 0.453592 | 1 lb = 0.453592 kg |
| Ounce | oz | 0.0283495 | 1 oz = 0.0283495 kg |

### Common Unit Groups

The following table lists typical unit groups you might configure in anyPIM:

| Group | Base Unit | Example Units |
|---|---|---|
| **Weight** | Kilogram (kg) | Gram, Milligram, Ton, Pound, Ounce |
| **Length** | Meter (m) | Centimeter, Millimeter, Kilometer, Inch, Foot |
| **Volume** | Liter (l) | Milliliter, Cubic meter, Gallon, Fluid ounce |
| **Area** | Square meter (m2) | Square centimeter, Square foot, Hectare |
| **Temperature** | Celsius (C) | Fahrenheit, Kelvin |
| **Electrical** | Volt (V) | Millivolt, Kilovolt |
| **Time** | Second (s) | Minute, Hour, Day |

::: warning Warning
Temperature conversions require offset-based formulas and cannot be represented by simple multiplication factors. If you need temperature conversion, use a custom conversion approach or store temperature values in a single unit only.
:::

## Assigning Units to Attributes

Units are connected to product attributes through the **Unit Group** property on attributes of type Float. When you create or edit a Float attribute, you can select a unit group from the dropdown.

Once a unit group is assigned:

- The product detail view displays a **unit selector** next to the attribute's input field.
- The user can choose the unit from the group's available units when entering a value.
- The stored value is internally converted to the base unit for consistent data storage.

### Example

An attribute called "Net Weight" is linked to the "Weight" unit group. When a user enters a product weight:

1. The user types `500` and selects `g` (Gram) from the unit dropdown.
2. The system stores the value internally as `0.5` kg (the base unit).
3. When another user views the product, they can switch the display to any unit in the group and the value converts automatically.

## Editing Units

To edit an existing unit, open the unit group detail view and click on the unit you want to modify. You can change the name, abbreviation, and conversion factor. If you change the conversion factor of a unit that is already in use, all existing attribute values using that unit will be affected during display, since the stored base-unit value remains unchanged.

::: danger Warning
Changing a conversion factor retroactively does not update values already stored in the database. Products that were entered using the old factor will display differently after the change. Review affected products after modifying conversion factors.
:::

## Deleting Units and Unit Groups

### Deleting a Unit

You can delete an individual unit from a group as long as it is not the base unit. If products currently use the deleted unit, their display unit will fall back to the base unit.

### Deleting a Unit Group

Deleting a unit group is only possible if no attributes are currently linked to it. If attributes depend on the group, you must first unlink them before deleting the group.

## Next Steps

- Learn how to create [Attributes](./attributes) of type Float and assign unit groups.
- Explore [Products](./products) to see how units appear in the product detail view.
- Review [Reports](../advanced/reports) to understand how unit values are handled in report outputs.
