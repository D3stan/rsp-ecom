<?php

test('a guest is sent to the login page from the signed-in checkout details', function () {
    $this->get(route('checkout.details'))->assertRedirect(route('login'));
});

test('a guest is sent to the login page when posting the signed-in cart checkout', function () {
    $this->post(route('checkout.cart'))->assertRedirect(route('login'));
});

test('the generic checkout route sends a guest to the guest checkout details', function () {
    $this->get(route('checkout'))->assertRedirect(route('guest.checkout.details'));
});
