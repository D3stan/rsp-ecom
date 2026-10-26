<?php

namespace App\Http\Controllers;

use App\Support\CookieDuration;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\TwitterCard;
use Inertia\Inertia;
use Inertia\Response;
use Whitecube\LaravelCookieConsent\CookiesManager;

class PageController extends Controller
{
    /**
     * Display the About page.
     */
    public function about(): Response
    {
        SEOMeta::setTitle('About Us – '.settings('store.name'));
        SEOMeta::setDescription('Learn more about '.settings('store.name').', our mission, values, and commitment to providing quality products and excellent customer service.');
        SEOMeta::setCanonical(route('about'));

        OpenGraph::setType('website');
        OpenGraph::setUrl(route('about'));
        OpenGraph::setTitle('About Us – '.settings('store.name'));
        OpenGraph::setDescription('Learn more about '.settings('store.name').', our mission, values, and commitment to providing quality products and excellent customer service.');

        TwitterCard::setType('summary');
        TwitterCard::setTitle('About Us – '.settings('store.name'));
        TwitterCard::setDescription('Learn more about '.settings('store.name').', our mission, values, and commitment to quality.');

        return Inertia::render('about');
    }

    /**
     * Display the Privacy Policy page.
     */
    public function privacy(): Response
    {
        SEOMeta::setTitle('Privacy Policy – '.settings('store.name'));
        SEOMeta::setDescription('Read our privacy policy to understand how we collect, use, and protect your personal information when you use our website and services.');
        SEOMeta::setCanonical(route('privacy'));

        OpenGraph::setType('website');
        OpenGraph::setUrl(route('privacy'));
        OpenGraph::setTitle('Privacy Policy – '.settings('store.name'));
        OpenGraph::setDescription('Read our privacy policy to understand how we collect, use, and protect your personal information.');

        TwitterCard::setType('summary');
        TwitterCard::setTitle('Privacy Policy – '.settings('store.name'));
        TwitterCard::setDescription('Read our privacy policy to understand how we collect, use, and protect your personal information.');

        return Inertia::render('privacy');
    }

    /**
     * Display the Cookie Policy page, listing every cookie registered in App\Providers\CookiesServiceProvider.
     */
    public function cookies(CookiesManager $cookies): Response
    {
        SEOMeta::setTitle('Cookie Policy – '.settings('store.name'));
        SEOMeta::setDescription('Every cookie and browser storage item this website uses, what it is for and how long it lasts.');
        SEOMeta::setCanonical(route('cookies'));

        OpenGraph::setType('website');
        OpenGraph::setUrl(route('cookies'));
        OpenGraph::setTitle('Cookie Policy – '.settings('store.name'));

        TwitterCard::setType('summary');
        TwitterCard::setTitle('Cookie Policy – '.settings('store.name'));

        return Inertia::render('cookies', [
            'categories' => array_map(fn ($category): array => [
                'key' => $category->key(),
                'title' => $category->title,
                'description' => $category->description,
                'cookies' => array_map(fn ($cookie): array => [
                    'name' => $cookie->name,
                    'description' => __($cookie->description),
                    'duration' => CookieDuration::forHumans($cookie->duration),
                ], $category->getCookies()),
            ], $cookies->getCategories()),
        ]);
    }

    /**
     * Display the Terms of Service page.
     */
    public function terms(): Response
    {
        SEOMeta::setTitle('Terms of Service – '.settings('store.name'));
        SEOMeta::setDescription('Review our terms of service to understand the rules and guidelines for using our website and purchasing our products.');
        SEOMeta::setCanonical(route('terms'));

        OpenGraph::setType('website');
        OpenGraph::setUrl(route('terms'));
        OpenGraph::setTitle('Terms of Service – '.settings('store.name'));
        OpenGraph::setDescription('Review our terms of service to understand the rules and guidelines for using our website and purchasing our products.');

        TwitterCard::setType('summary');
        TwitterCard::setTitle('Terms of Service – '.settings('store.name'));
        TwitterCard::setDescription('Review our terms of service to understand the rules and guidelines for using our website.');

        return Inertia::render('terms');
    }

    /**
     * Display the Shipping & Returns page.
     */
    public function shippingReturns(): Response
    {
        SEOMeta::setTitle('Shipping & Returns – '.settings('store.name'));
        SEOMeta::setDescription('Learn about our shipping options, delivery times, return policy, and how to exchange or return products you\'ve purchased.');
        SEOMeta::setCanonical(route('shipping-returns'));

        OpenGraph::setType('website');
        OpenGraph::setUrl(route('shipping-returns'));
        OpenGraph::setTitle('Shipping & Returns – '.settings('store.name'));
        OpenGraph::setDescription('Learn about our shipping options, delivery times, return policy, and how to exchange or return products.');

        TwitterCard::setType('summary');
        TwitterCard::setTitle('Shipping & Returns – '.settings('store.name'));
        TwitterCard::setDescription('Learn about our shipping options, delivery times, and return policy.');

        return Inertia::render('shipping-returns');
    }

    /**
     * Display the FAQ page.
     */
    public function faq(): Response
    {
        SEOMeta::setTitle('Frequently Asked Questions – '.settings('store.name'));
        SEOMeta::setDescription('Find answers to commonly asked questions about our products, ordering process, shipping, returns, and customer service.');
        SEOMeta::setCanonical(route('faq'));

        OpenGraph::setType('website');
        OpenGraph::setUrl(route('faq'));
        OpenGraph::setTitle('Frequently Asked Questions – '.settings('store.name'));
        OpenGraph::setDescription('Find answers to commonly asked questions about our products, ordering process, shipping, returns, and customer service.');

        TwitterCard::setType('summary');
        TwitterCard::setTitle('FAQ – '.settings('store.name'));
        TwitterCard::setDescription('Find answers to commonly asked questions about our products, ordering, and services.');

        return Inertia::render('faq');
    }
}
