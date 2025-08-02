<x-mail::message>
# Benvenuto nel nostro store! 🎉

Ciao {{ $user->name }},

Grazie per esserti registrato al nostro store! Siamo entusiasti di averti come nuovo cliente.

## Cosa puoi fare ora:

✅ **Sfoglia i nostri prodotti** - Scopri la nostra collezione di prodotti di alta qualità
✅ **Aggiungi ai preferiti** - Salva i prodotti che ti piacciono per dopo
✅ **Checkout veloce** - I tuoi dati sono salvati per acquisti più rapidi
✅ **Traccia i tuoi ordini** - Monitora lo stato dei tuoi acquisti

<x-mail::button :url="route('products.index')">
Inizia a fare shopping
</x-mail::button>

## Hai bisogno di aiuto?

Se hai domande o hai bisogno di assistenza, non esitare a contattarci:

- **Email:** {{ config('mail.from.address') }}
- **Telefono:** +39 XXX XXX XXXX

<x-mail::button :url="route('contact')">
Contattaci
</x-mail::button>

Grazie ancora per aver scelto il nostro store!

Cordiali saluti,<br>
{{ config('app.name') }}
</x-mail::message>