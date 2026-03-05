## Contesto
Il progetto è un ecommerce Laravel + React/Inertia con principi UX dichiarati in `ecommerce_structure.md`:
- mobile-first
- navigazione chiara
- esperienza semplice e affidabile su creazione e consultazione annunci/prodotti

I problemi sotto indicano dove la struttura/UX attuale non rispetta questi principi.

## Priorità interventi
1. Upload foto annunci (bloccante)
2. Condivisione annuncio
3. Modello faro come variante annuncio
4. Formattazione testo annuncio su mobile

## Problemi dettagliati

### 1) Upload foto annunci con molte immagini (Priorità 1)
**Problema attuale**
- Pubblicando un annuncio con molte foto (es. 7), l’upload fallisce o non salva tutte le immagini.
- Con poche foto (es. 3), l’annuncio viene pubblicato correttamente.
- Workaround attuale: pubblicare con poche foto e aggiungere le altre in modifica.

**Perché è un problema strutturale**
- Il flusso di creazione annuncio non è robusto nel caso reale d’uso (utente carica molte immagini subito).
- Il comportamento è incoerente tra “creazione” e “modifica” annuncio.
- Mancano feedback chiari all’utente in caso di fallimento upload multiplo.

**Comportamento atteso**
- La creazione annuncio deve supportare in modo affidabile più immagini nello stesso invio.
- Se una o più immagini falliscono, l’utente deve ricevere un errore esplicito e azionabile.

**Impatto**
- Alto: blocca la pubblicazione corretta degli annunci e riduce conversione/qualità listing.

---

### 2) Condivisione annuncio non funzionante (Priorità 2)
**Problema attuale**
- Premendo “condividi” su un annuncio, la condivisione non parte o non produce risultato utile.

**Perché è un problema strutturale**
- Funzione di distribuzione traffico non affidabile (social, chat, passaparola).
- Rottura nel funnel discovery → visita annuncio.

**Comportamento atteso**
- Il pulsante “condividi” deve aprire il flusso di condivisione corretto (web share o fallback link copiato).
- Deve condividere URL valido dell’annuncio e metadati coerenti (titolo/anteprima dove disponibile).

**Impatto**
- Medio/Alto: riduce acquisizione utenti e visibilità organica degli annunci.

---

### 3) Selezione modello faro come variante annuncio (Priorità 3)
**Decisione funzionale**
- Il modello faro va gestito come **variante prodotto/annuncio** (può influenzare prezzo e stock), non come semplice filtro visivo.

**Problema attuale**
- Negli annunci manca la possibilità di selezionare il modello faro (es. rugoso/carbon).
- Non c’è associazione chiara tra variante scelta e immagini corrispondenti.

**Perché è un problema strutturale**
- Manca un livello dati fondamentale nelle inserzioni (varianti).
- L’utente non capisce se sta vedendo la configurazione corretta del prodotto.

**Comportamento atteso**
- In creazione/modifica annuncio si seleziona il modello faro.
- In visualizzazione annuncio, cambiando variante, la galleria mostra foto coerenti con quella variante.
- Prezzo/stock devono poter essere differenziati per variante se previsto.

**Impatto**
- Medio: riduce chiarezza prodotto e può generare ordini errati o richieste di supporto.

---

### 4) Formattazione testo annuncio su mobile (Priorità 4)
**Problema attuale**
- Su telefono, il testo degli annunci risulta spesso troppo “pieno”/poco leggibile.
- Gli utenti (in larga maggioranza mobile) faticano a capire subito cosa stanno guardando.

**Perché è un problema strutturale**
- Non allineato al principio mobile-first definito nel progetto.
- Gerarchia visiva debole: informazioni chiave non emergono rapidamente.

**Comportamento atteso**
- Testo annuncio leggibile su mobile con sintesi iniziale chiara.
- Struttura del contenuto che evidenzi subito: compatibilità/modello, condizione, variante, prezzo.

**Impatto**
- Medio: peggiora UX mobile e comprensione immediata dell’annuncio.

## Nota di allineamento prodotto/UX
Questi 4 punti non sono solo bug isolati: evidenziano gap nella struttura attuale su tre aree critiche:
- **Affidabilità flussi core** (upload e condivisione)
- **Modello dati annuncio** (varianti reali, non solo immagini)
- **Presentazione mobile-first** (leggibilità e comprensione rapida)