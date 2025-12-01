# Premium Classifieds (Pay-to-Contact)

Profesjonalna wtyczka ogłoszeniowa typu *Pay-to-Contact* (model RentAFriend) dla WordPress.

## Funkcje
- Ogłoszenia (CPT `pc_listing`) z galerią zdjęć
- Panel użytkownika (`[premium_dashboard]`) — Moje ogłoszenia, Edytor, Wiadomości, Ulubione, Historia płatności
- Płatności Stripe (PaymentIntent / Checkout) i PayPal (email) — natywna implementacja (bez WooCommerce)
- Webhooky Stripe -> automatyczne aktywowanie funkcji po opłaceniu
- Role: `pc_seller`, `pc_buyer`
- Admin: ustawienia, moderacja, eksport CSV/JSON

## Wymagania
- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+
- Composer (zalecane) do instalacji `stripe/stripe-php`
- mod_rewrite włączony

## Instalacja
1. Skopiuj folder wtyczki do `wp-content/plugins/premium-classifieds`.
2. (Opcjonalnie) Uruchom `composer install` w katalogu wtyczki, aby zainstalować Stripe SDK.
3. Aktywuj wtyczkę w panelu WP.
4. Przejdź do **Przyjaciel na Godzinę > Ustawienia** i skonfiguruj klucze Stripe / PayPal oraz ceny.

## Stripe
- Zainstaluj Stripe PHP SDK: `composer require stripe/stripe-php`
- Ustaw klucze API w ustawieniach wtyczki.
- Skonfiguruj webhook w panelu Stripe kierujący na: `https(s)://your-site.com/wp-json/pc/v1/webhook` i wklej sekret do ustawień.

## Bezpieczeństwo
- Wszystkie formularze używają nonce.
- Wejścia są sanityzowane (Helpers).
- Webhooki Stripe weryfikowane przy pomocy sekretu.

## Rozwój
- Struktura oparta na OOP i PSR-4 (autoload).
- Pliki testowe w `tests/` (skeleton PHPUnit included).

## Licencja
Stworzono z ❤️ — dostosuj licencję według potrzeb.
