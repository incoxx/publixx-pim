/**
 * Uebersetzungen fuer das Media-Modul: views/media/MediaView.vue und
 * components/media/*.vue (VideoPreview, AudioPlayer, MediaUploadQueue,
 * MediaProcessingStatus). Gleicher Rohtext-als-Key-Ansatz wie die anderen
 * locales/*RawText.js-Dateien.
 */

const en = {
  // --- Ordner-Sidebar ---
  'Alle Medien': 'All Media',
  'Neuer Ordner': 'New folder',
  'Ordnername…': 'Folder name…',
  'OK': 'OK',
  'Unterordner erstellen': 'Create subfolder',
  'Ordner konnte nicht gelöscht werden': 'Folder could not be deleted',
  'Ordner konnte nicht erstellt werden': 'Folder could not be created',

  // --- Toolbar ---
  'Suchen…': 'Search…',
  'Schlagwort…': 'Keyword…',
  'Titel…': 'Title…',
  'Dateiname…': 'File name…',
  'Nach Schlagwort filtern (exakt, mehrere kommagetrennt)': 'Filter by keyword (exact, multiple comma-separated)',
  'Hochgeladen': 'Uploaded',
  'Titel': 'Title',
  'Dateiname': 'File name',
  'Dateigröße': 'File size',
  'Aufsteigend': 'Ascending',
  'Absteigend': 'Descending',
  'Print': 'Print',
  'Web': 'Web',
  'Print & Web': 'Print & Web',
  'Nicht vorhanden': 'Missing',
  'Filter: Nur fehlende Assets (aktiv)': 'Filter: Only missing assets (active)',
  'Nur fehlende Assets anzeigen': 'Show only missing assets',
  'Renditions': 'Renditions',
  'Generierte Motiv-Renditions werden angezeigt': 'Generated motif renditions are shown',
  'Generierte Motiv-Renditions sind ausgeblendet': 'Generated motif renditions are hidden',
  'Inkl. Unterordner': 'Incl. subfolders',
  'Inkl. Unterordner (aktiv)': 'Incl. subfolders (active)',
  'Nur dieser Ordner': 'This folder only',
  'Als Excel exportieren': 'Export as Excel',
  'Auto-Match': 'Auto-Match',
  'Auto-Match: Dateinamen → SKU': 'Auto-Match: File names → SKU',
  'Bulk-Import': 'Bulk Import',
  'Bulk-Import über Excel': 'Bulk Import via Excel',
  'Import über URL': 'Import via URL',
  'URL-Import': 'URL Import',
  'Wird hochgeladen...': 'Uploading...',

  // --- Selection toolbar / ZIP / Recover / Re-Link ---
  'Medien ausgewählt (über alle Seiten).': 'media items selected (across all pages).',
  'In Ordner verschieben': 'Move to folder',
  'Als ZIP herunterladen': 'Download as ZIP',
  'ZIP': 'ZIP',
  'Produkt-Zuordnungen wiederherstellen': 'Restore product assignments',
  'Re-Link': 'Re-Link',
  'Dateien von URL wiederherstellen': 'Restore files from URL',
  'Recover': 'Recover',
  'ZIP wird erstellt…': 'Creating ZIP…',
  'Dateien': 'Files',
  'Medien auf dieser Seite ausgewählt.': 'media items on this page selected.',
  'Lade…': 'Loading…',
  'Alle {total} Medien auswählen': 'Select all {total} media',

  // --- Grid / List / Empty state ---
  'Datei fehlt': 'File missing',
  'Details': 'Details',
  'Größe': 'Size',
  'Verwendung': 'Usage',
  'Keine Medien vorhanden': 'No media available',
  'Dateien hierhin ziehen oder "Hochladen" klicken': 'Drag files here or click "Upload"',

  // --- Detail panel ---
  'Master eines Motivs mit weiteren Renditions (Print/Web/Mobile/Social)':
    'Master of a motif with further renditions (print/web/mobile/social)',
  'Download': 'Download',
  'Kopiert!': 'Copied!',
  'URL kopieren': 'Copy URL',
  'Importiert von': 'Imported from',
  'Beschreibung (DE)': 'Description (DE)',
  'Beschreibung (EN)': 'Description (EN)',
  'Titel (EN)': 'Title (EN)',
  'Schlagworte': 'Keywords',
  'Schlagwort hinzufügen…': 'Add keyword…',
  'Sprache': 'Language',
  'Land': 'Country',
  'Verwendungsnachweis': 'Usage references',
  'Produkte (': 'Products (',
  'Kategorien / Knoten (': 'Categories / Nodes (',
  'Nicht verwendet': 'Not used',
  'Letzter Upload': 'Last upload',
  'Dateiversionen (': 'File versions (',
  'Rev.': 'Rev.',
  'KB': 'KB',

  // --- Dialogs: Medium/Ordner loeschen, Verschieben ---
  'Medium löschen?': 'Delete media item?',
  "Die Datei '{name}' wird unwiderruflich gelöscht.": "The file '{name}' will be permanently deleted.",
  'Ordner löschen?': 'Delete folder?',
  "Der Ordner '{name}' und alle Unterordner werden gelöscht. Medien bleiben erhalten.":
    "The folder '{name}' and all subfolders will be deleted. Media will be kept.",
  '{count} Medium verschieben': 'Move {count} media item',
  '{count} Medien verschieben': 'Move {count} media items',
  'Zielordner': 'Target folder',
  '— Kein Ordner (Stammverzeichnis) —': '— No folder (root) —',
  'Verschiebe…': 'Moving…',
  'Verschieben': 'Move',

  // --- URL Import Dialog ---
  'Über URL importieren': 'Import via URL',
  'Bild- oder YouTube-URL': 'Image or YouTube URL',
  'https://example.com/bild.jpg oder https://youtube.com/watch?v=...': 'https://example.com/image.jpg or https://youtube.com/watch?v=...',
  'YouTube-Video erkannt — wird per Video-Import heruntergeladen (kann einige Minuten dauern).':
    'YouTube video detected — will be downloaded via video import (may take a few minutes).',
  'Bildtyp': 'Image type',
  '— Kein Typ —': '— No type —',
  'Importieren': 'Import',
  'Importiere…': 'Importing…',

  // --- Bulk Import Dialog ---
  'Excel-Datei (.xlsx) mit einer Spalte namens': 'Upload an Excel file (.xlsx) with a column named',
  'hochladen. Alle Bilder werden heruntergeladen und importiert.': '. All images will be downloaded and imported.',
  'Excel-Datei': 'Excel file',
  'importiert,': 'imported,',
  'fehlgeschlagen': 'failed',
  'Schließen': 'Close',

  // --- Auto-Match Dialog ---
  'Ordnet Medien automatisch Produkten zu, wenn der Dateiname per Regex zur SKU passt.':
    'Automatically assigns media to products when the file name matches the SKU via regex.',
  'Die erste Capture-Group': 'The first capture group',
  'wird als SKU verwendet.': 'is used as SKU.',
  'Regex-Muster': 'Regex pattern',
  '/^(.+?)(?:_\\d+)?$/': '/^(.+?)(?:_\\d+)?$/',
  'Beispiel:': 'Example:',
  'extrahiert "ABC123" aus "ABC123_1.jpg"': 'extracts "ABC123" from "ABC123_1.jpg"',
  'Nur Vorschau (Dry Run)': 'Preview only (dry run)',
  'ohne SKU': 'without SKU',
  'Vorschau': 'Preview',
  'Ausgeführt': 'Executed',
  'Zuordnungen:': 'Assignments:',
  'Nicht zugeordnet:': 'Not assigned:',
  'Ausführen': 'Execute',
  'Matching…': 'Matching…',

  // --- Recover from URL Dialog ---
  'Recover from URL': 'Recover from URL',
  '{count} Asset wiederherstellen.': 'Restore {count} asset.',
  '{count} Assets wiederherstellen.': 'Restore {count} assets.',
  'Die Datei wird als': 'The file is fetched as',
  'Base-URL + Dateiname': 'Base URL + filename',
  'abgerufen und unter dem bestehenden Pfad gespeichert.': 'and saved under the existing path.',
  'Base URL': 'Base URL',
  'https://cdn.example.com/assets/': 'https://cdn.example.com/assets/',
  '/dateiname.jpg': '/filename.jpg',
  'Wiederherstellen': 'Restore',
  'Wiederherstellung fehlgeschlagen': 'Restore failed',

  // --- Bulk Delete Confirm ---
  'Medien löschen': 'Delete media',
  '{count} Medium endgültig löschen? Dieser Vorgang kann nicht rückgängig gemacht werden.':
    'Delete {count} media item permanently? This action cannot be undone.',
  '{count} Medien endgültig löschen? Dieser Vorgang kann nicht rückgängig gemacht werden.':
    'Delete {count} media items permanently? This action cannot be undone.',
  'Auch Medien mit Produkt-Zuordnungen löschen': 'Also delete media with product assignments',
  '(Zuordnungen werden aufgehoben)': '(assignments are removed)',
  '… und': '… and',
  'weitere': 'more',
  'Trotzdem löschen': 'Delete anyway',

  // --- Errors ---
  'ZIP-Download fehlgeschlagen': 'ZIP download failed',
  'Löschen fehlgeschlagen': 'Delete failed',
  'Re-Link fehlgeschlagen': 'Re-link failed',
  'Speichern fehlgeschlagen': 'Save failed',
  'Excel-Export fehlgeschlagen': 'Excel export failed',

  // --- Enum-Werte (identisch angezeigt, technische Begriffe) ---
  'image': 'image',
  'document': 'document',
  'video': 'video',
  'audio': 'audio',
  'other': 'other',
  'P&W': 'P&W',
  'Audio': 'Audio',
  'Video': 'Video',

  // --- VideoPreview.vue / AudioPlayer.vue ---
  'Thumbnail wird erstellt...': 'Thumbnail is being created...',
  'Verarbeitung wird vorbereitet...': 'Processing is being prepared...',
  'Verarbeitung fehlgeschlagen': 'Processing failed',
  'Wird verarbeitet...': 'Processing...',

  // --- MediaUploadQueue.vue ---
  'Upload fehlgeschlagen': 'Upload failed',
  'hochgeladen': 'uploaded',
  'repariert': 'repaired',
  'überschrieben': 'overwritten',
  'Erneut versuchen': 'Retry',

  // --- MediaProcessingStatus.vue ---
  'PDF-Verarbeitung:': 'PDF processing:',
  'aktiv': 'active',
  'wartend': 'pending',
  'fertig': 'ready',
  '{count} PDF verarbeitet': '{count} PDF processed',
  '{count} PDFs verarbeitet': '{count} PDFs processed',
  'Audio/Video-Verarbeitung:': 'Audio/Video processing:',
  '{count} Audio/Video-Datei verarbeitet': '{count} audio/video file processed',
  '{count} Audio/Video-Dateien verarbeitet': '{count} audio/video files processed',
};

const de = Object.fromEntries(Object.keys(en).map((key) => [key, key]));

export const mediaRawText = { de, en };
