/**
 * Custom vue-i18n messageResolver fuer den Rohtext-Ansatz (PoC).
 *
 * Automatisch extrahierte Keys sind ganze deutsche Saetze/Phrasen und
 * enthalten oft Punkte, Klammern, Anfuehrungszeichen ("Keine Knoten gefunden.").
 * Der vue-i18n-Standardresolver interpretiert Punkte im Key aber als
 * Pfad-Trenner (verschachtelter Objektzugriff wie bei "nav.save") und wuerde
 * solche Keys daher falsch/gar nicht aufloesen.
 *
 * Deshalb: zuerst exakter Flat-Key-Treffer versuchen (deckt die Rohtext-Keys
 * ab), erst danach auf die klassische Punkt-Pfad-Aufloesung zurueckfallen
 * (deckt die bestehenden strukturierten Keys wie t('nav.save') ab).
 */
export function rawTextMessageResolver(obj, path) {
  if (obj != null && Object.prototype.hasOwnProperty.call(obj, path)) {
    return obj[path];
  }

  if (obj == null) return null;

  return path.split('.').reduce((acc, segment) => {
    if (acc == null) return undefined;
    return acc[segment];
  }, obj);
}
