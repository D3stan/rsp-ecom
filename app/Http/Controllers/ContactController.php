<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\TwitterCard;

class ContactController extends Controller
{
    public function index()
    {
        // Set SEO for contact page
        SEOMeta::setTitle('Contact Us – ' . config('app.name'));
        SEOMeta::setDescription('Get in touch with our customer service team. We\'re here to help with any questions about our products or services.');
        SEOMeta::setCanonical(route('contact'));

        OpenGraph::setType('website');
        OpenGraph::setUrl(route('contact'));
        OpenGraph::setTitle('Contact Us – ' . config('app.name'));
        OpenGraph::setDescription('Get in touch with our customer service team. We\'re here to help with any questions about our products or services.');

        TwitterCard::setType('summary');
        TwitterCard::setTitle('Contact Us – ' . config('app.name'));
        TwitterCard::setDescription('Get in touch with our customer service team. We\'re here to help with any questions.');

        return Inertia::render('contact');
    }

    public function store(Request $request)
    {
        // Values that end up in mail headers are single-line; line breaks are never legitimate there.
        $input = $request->all();
        foreach (['firstName', 'lastName', 'subject'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = ContactMessage::singleLine($input[$field]);
            }
        }

        $validator = Validator::make($input, [
            'firstName' => 'required|string|max:100',
            'lastName' => 'required|string|max:100',
            'email' => 'required|email|max:254',
            'subject' => 'required|string|max:150',
            'message' => 'required|string|min:10|max:5000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();
        
        // Get contact email from environment or fallback
        $contactEmail = config('mail.contact_email', config('mail.from.address'));
        
        try {
            // Send email to the contact address
            Mail::to($contactEmail)->send(new ContactMessage($validated));
            
            return back()->with('success', 'Thank you for your message! We\'ll get back to you soon.');
        } catch (\Exception $e) {
            \Log::error('Contact form submission failed', ['exception' => get_class($e), 'error' => $e->getMessage()]);
            
            return back()->with('error', 'Sorry, there was an error sending your message. Please try again later or contact us directly.');
        }
    }
}
