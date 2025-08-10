<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\TwitterCard;

class PageController extends Controller
{
    /**
     * Display the About page.
     */
    public function about(): Response
    {
        SEOMeta::setTitle('About Us – ' . config('app.name'));
        SEOMeta::setDescription('Learn more about ' . config('app.name') . ', our mission, values, and commitment to providing quality products and excellent customer service.');
        SEOMeta::setCanonical(route('about'));

        OpenGraph::setType('website');
        OpenGraph::setUrl(route('about'));
        OpenGraph::setTitle('About Us – ' . config('app.name'));
        OpenGraph::setDescription('Learn more about ' . config('app.name') . ', our mission, values, and commitment to providing quality products and excellent customer service.');

        TwitterCard::setType('summary');
        TwitterCard::setTitle('About Us – ' . config('app.name'));
        TwitterCard::setDescription('Learn more about ' . config('app.name') . ', our mission, values, and commitment to quality.');

        return Inertia::render('about');
    }

    /**
     * Display the Privacy Policy page.
     */
    public function privacy(): Response
    {
        SEOMeta::setTitle('Privacy Policy – ' . config('app.name'));
        SEOMeta::setDescription('Read our privacy policy to understand how we collect, use, and protect your personal information when you use our website and services.');
        SEOMeta::setCanonical(route('privacy'));

        OpenGraph::setType('website');
        OpenGraph::setUrl(route('privacy'));
        OpenGraph::setTitle('Privacy Policy – ' . config('app.name'));
        OpenGraph::setDescription('Read our privacy policy to understand how we collect, use, and protect your personal information.');

        TwitterCard::setType('summary');
        TwitterCard::setTitle('Privacy Policy – ' . config('app.name'));
        TwitterCard::setDescription('Read our privacy policy to understand how we collect, use, and protect your personal information.');

        return Inertia::render('privacy');
    }

    /**
     * Display the Terms of Service page.
     */
    public function terms(): Response
    {
        SEOMeta::setTitle('Terms of Service – ' . config('app.name'));
        SEOMeta::setDescription('Review our terms of service to understand the rules and guidelines for using our website and purchasing our products.');
        SEOMeta::setCanonical(route('terms'));

        OpenGraph::setType('website');
        OpenGraph::setUrl(route('terms'));
        OpenGraph::setTitle('Terms of Service – ' . config('app.name'));
        OpenGraph::setDescription('Review our terms of service to understand the rules and guidelines for using our website and purchasing our products.');

        TwitterCard::setType('summary');
        TwitterCard::setTitle('Terms of Service – ' . config('app.name'));
        TwitterCard::setDescription('Review our terms of service to understand the rules and guidelines for using our website.');

        return Inertia::render('terms');
    }

    /**
     * Display the Shipping & Returns page.
     */
    public function shippingReturns(): Response
    {
        SEOMeta::setTitle('Shipping & Returns – ' . config('app.name'));
        SEOMeta::setDescription('Learn about our shipping options, delivery times, return policy, and how to exchange or return products you\'ve purchased.');
        SEOMeta::setCanonical(route('shipping-returns'));

        OpenGraph::setType('website');
        OpenGraph::setUrl(route('shipping-returns'));
        OpenGraph::setTitle('Shipping & Returns – ' . config('app.name'));
        OpenGraph::setDescription('Learn about our shipping options, delivery times, return policy, and how to exchange or return products.');

        TwitterCard::setType('summary');
        TwitterCard::setTitle('Shipping & Returns – ' . config('app.name'));
        TwitterCard::setDescription('Learn about our shipping options, delivery times, and return policy.');

        return Inertia::render('shipping-returns');
    }

    /**
     * Display the FAQ page.
     */
    public function faq(): Response
    {
        SEOMeta::setTitle('Frequently Asked Questions – ' . config('app.name'));
        SEOMeta::setDescription('Find answers to commonly asked questions about our products, ordering process, shipping, returns, and customer service.');
        SEOMeta::setCanonical(route('faq'));

        OpenGraph::setType('website');
        OpenGraph::setUrl(route('faq'));
        OpenGraph::setTitle('Frequently Asked Questions – ' . config('app.name'));
        OpenGraph::setDescription('Find answers to commonly asked questions about our products, ordering process, shipping, returns, and customer service.');

        TwitterCard::setType('summary');
        TwitterCard::setTitle('FAQ – ' . config('app.name'));
        TwitterCard::setDescription('Find answers to commonly asked questions about our products, ordering, and services.');

        return Inertia::render('faq');
    }
}
