{{-- Plain-text twin of withdrawal.blade.php. Output is not HTML-escaped on purpose. --}}
{!! __('emails.withdrawal.title') !!}
{!! __('emails.withdrawal.body', ['days' => (int) config('shipping.withdrawal_days')]) !!}
{!! __('emails.withdrawal.how', ['address' => config('mail.support_address')]) !!}
{!! __('emails.links.terms') !!}: {!! route('terms') !!}
{!! __('emails.links.returns') !!}: {!! route('shipping-returns') !!}
{!! __('emails.links.privacy') !!}: {!! route('privacy') !!}
