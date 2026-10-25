import StateField from '@/components/address/StateField';
import MarketingConsentCheckbox from '@/components/checkout/MarketingConsentCheckbox';
import OrderSummary, { type SummaryLine } from '@/components/checkout/OrderSummary';
import Header from '@/components/header';
import InputError from '@/components/input-error';
import LoadingOverlay from '@/components/LoadingOverlay';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/contexts/ToastContext';
import { useIsMobile } from '@/hooks/use-mobile';
import { useMoney } from '@/hooks/use-money';
import { useSetting } from '@/hooks/use-setting';
import { useTranslation } from '@/hooks/useTranslation';
import { lookupItalianPostcode, provinceCode } from '@/lib/italian-address';
import { SharedData } from '@/types';
import { Page } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, ChevronUp, CreditCard, FileText, Tag, Truck } from 'lucide-react';
import { type FormEvent, useEffect, useRef, useState } from 'react';

interface Cart {
    id: number;
}

interface ShippingAddress {
    [key: string]: string | undefined;
    first_name: string;
    last_name: string;
    company?: string;
    address_line_1: string;
    address_line_2?: string;
    city: string;
    state: string;
    postal_code: string;
    country: string;
    phone?: string;
}

interface SavedAddress {
    id: number;
    is_default: boolean;
    [key: string]: string | number | boolean | null;
}

interface ContactInformation {
    email: string;
}

const FIELDS: ReadonlyArray<readonly [string, string]> = [
    ['email', 'email'],
    ['shipping_address.first_name', 'first_name'],
    ['shipping_address.last_name', 'last_name'],
    ['shipping_address.company', 'company'],
    ['shipping_address.address_line_1', 'address_line_1'],
    ['shipping_address.address_line_2', 'address_line_2'],
    ['shipping_address.city', 'city'],
    ['shipping_address.state', 'state'],
    ['shipping_address.postal_code', 'postal_code'],
    ['shipping_address.country', 'country'],
    ['shipping_address.phone', 'phone'],
    ['order_notes', 'order_notes'],
];

const REQUIRED_ADDRESS_FIELDS = ['first_name', 'last_name', 'address_line_1', 'city', 'state', 'postal_code', 'country', 'phone'] as const;

interface CheckoutDetailsProps extends SharedData {
    cart: Cart | null;
    cartItems: SummaryLine[];
    subtotal: number;
    shippingCost: number;
    taxAmount: number;
    taxRate: number | null;
    discountAmount?: number;
    couponCode?: string;
    discountDescription?: string;
    total: number;
    totalItems: number;
    freeShippingRemaining?: number | null;
    savedAddresses?: SavedAddress[];
    provinces: Record<string, string>;
}

const isPhoneValid = (value: string): boolean => {
    const digits = value.replace(/\D/g, '').length;

    return /^\+?[\d\s().\/-]+$/.test(value) && digits >= 6 && digits <= 15;
};

export default function CheckoutDetails() {
    const pageProps = usePage<CheckoutDetailsProps>().props;
    const { cartItems, subtotal, shippingCost, taxAmount, taxRate, discountAmount = 0, couponCode: initialCouponCode, discountDescription, total, totalItems, freeShippingRemaining, savedAddresses = [], provinces, auth } = pageProps;
    const { t, isLoading, locale } = useTranslation();
    const formatCurrency = useMoney();

    const orderNotesMax = useSetting('orders.order_notes_max', 500);
    const minAmount = useSetting('orders.min_amount', 0);
    const destinations = useSetting('shipping.destinations', ['IT']);
    const defaultCountry = useSetting('shipping.default_country', 'IT');
    const belowMinimum = minAmount > 0 && subtotal < minAmount;
    const countryName = (code: string): string => {
        try {
            return new Intl.DisplayNames([locale], { type: 'region' }).of(code) ?? code;
        } catch {
            return code;
        }
    };
    const isMobile = useIsMobile();
    const { addToast } = useToast();

    const [isContactOpen, setIsContactOpen] = useState(true);
    const [isShippingOpen, setIsShippingOpen] = useState(true);
    const [isDiscountOpen, setIsDiscountOpen] = useState(false);
    const [isNotesOpen, setIsNotesOpen] = useState(false);
    const [isSummaryOpen, setIsSummaryOpen] = useState(false);

    const [contactInfo, setContactInfo] = useState<ContactInformation>({
        email: auth?.user?.email || '',
    });

    const addressForm = (saved?: SavedAddress): ShippingAddress => {
        const text = (key: string) => (typeof saved?.[key] === 'string' ? (saved[key] as string) : '');
        const country = text('country') && destinations.includes(text('country')) ? text('country') : defaultCountry;

        return {
            first_name: text('first_name') || auth?.user?.name?.split(' ')[0] || '',
            last_name: text('last_name') || auth?.user?.name?.split(' ').slice(1).join(' ') || '',
            company: text('company'),
            address_line_1: text('address_line_1'),
            address_line_2: text('address_line_2'),
            city: text('city'),
            state: country === 'IT' ? provinceCode(provinces, text('state')) : text('state'),
            postal_code: text('postal_code'),
            country,
            phone: text('phone'),
        };
    };

    const defaultAddress = savedAddresses.find((address) => address.is_default) ?? savedAddresses[0];
    const [selectedAddress, setSelectedAddress] = useState<string>(defaultAddress ? String(defaultAddress.id) : 'new');
    const [saveAddress, setSaveAddress] = useState(true);
    const [shippingAddress, setShippingAddress] = useState<ShippingAddress>(() => addressForm(defaultAddress));
    const autofilled = useRef({ city: false, state: false });

    const [couponCode, setCouponCode] = useState(initialCouponCode || '');
    const [orderNotes, setOrderNotes] = useState('');
    const [marketingConsent, setMarketingConsent] = useState(false);
    const [isApplyingCoupon, setIsApplyingCoupon] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);
    useEffect(() => {
        const onPageShow = (event: PageTransitionEvent) => {
            if (event.persisted) setIsProcessing(false);
        };
        window.addEventListener('pageshow', onPageShow);

        return () => window.removeEventListener('pageshow', onPageShow);
    }, []);

    const [errors, setErrors] = useState<Record<string, string>>({});

    const clearError = (key: string) => {
        setErrors((prev) => {
            if (!(key in prev)) return prev;
            const next = { ...prev };
            delete next[key];
            return next;
        });
    };

    const invalidProps = (key: string, id: string) => (errors[key] ? { 'aria-invalid': true as const, 'aria-describedby': `${id}-error` } : {});

    const showFieldErrors = (fieldErrors: Record<string, string>) => {
        setErrors(fieldErrors);
        const keys = Object.keys(fieldErrors);
        if (keys.includes('email')) setIsContactOpen(true);
        if (keys.some((key) => key.startsWith('shipping_address.'))) setIsShippingOpen(true);
        if (keys.includes('order_notes')) setIsNotesOpen(true);

        const first = FIELDS.find(([key]) => key in fieldErrors);
        if (first) {
            setTimeout(() => document.getElementById(first[1])?.focus(), 0);
        }
    };

    const validate = (): Record<string, string> => {
        const found: Record<string, string> = {};
        const email = contactInfo.email.trim();

        if (!email) {
            found.email = t('checkout.field_required');
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            found.email = t('checkout.email_invalid');
        }

        for (const field of REQUIRED_ADDRESS_FIELDS) {
            if (!shippingAddress[field]?.trim()) {
                found[`shipping_address.${field}`] = t('checkout.field_required');
            }
        }

        if (shippingAddress.phone?.trim() && !isPhoneValid(shippingAddress.phone.trim())) {
            found['shipping_address.phone'] = t('checkout.phone_invalid');
        }

        return found;
    };

    const handleServerErrors = (serverErrors: Record<string, string>) => {
        const fieldKeys = new Set(FIELDS.map(([key]) => key));
        const fieldErrors = Object.fromEntries(Object.entries(serverErrors).filter(([key]) => fieldKeys.has(key)));
        const otherErrors = Object.entries(serverErrors).filter(([key]) => !fieldKeys.has(key));

        showFieldErrors(fieldErrors);

        if (otherErrors.length > 0 || Object.keys(fieldErrors).length === 0) {
            addToast({
                type: 'error',
                title: t('cart.checkout_error'),
                description: otherErrors[0]?.[1] || t('cart.checkout_error_description'),
            });
        }
        setIsProcessing(false);
    };

    const updateShippingAddress = (field: keyof ShippingAddress, value: string) => {
        clearError(`shipping_address.${String(field)}`);
        if (field === 'city' || field === 'state') autofilled.current[field] = false;
        setShippingAddress((prev) => ({
            ...prev,
            [field]: value,
            ...(field === 'country' && value === 'IT' ? { state: provinceCode(provinces, prev.state) } : {}),
        }));
    };

    const updatePostalCode = (value: string) => {
        updateShippingAddress('postal_code', value);

        if (shippingAddress.country !== 'IT') return;

        void lookupItalianPostcode(value.trim()).then((hit) => {
            if (!hit) return;

            setShippingAddress((prev) => {
                if (prev.postal_code.trim() !== value.trim()) return prev;

                const next = { ...prev };
                if (hit.city && (!prev.city || autofilled.current.city)) {
                    next.city = hit.city;
                    autofilled.current.city = true;
                }
                if (!prev.state || autofilled.current.state) {
                    next.state = hit.state;
                    autofilled.current.state = true;
                }

                return next;
            });
            clearError('shipping_address.city');
            clearError('shipping_address.state');
        });
    };

    const selectSavedAddress = (id: string) => {
        setSelectedAddress(id);
        autofilled.current = { city: false, state: false };
        setErrors((prev) => Object.fromEntries(Object.entries(prev).filter(([key]) => !key.startsWith('shipping_address.'))));
        setShippingAddress(addressForm(savedAddresses.find((address) => String(address.id) === id)));
    };

    const updateContactInfo = (field: keyof ContactInformation, value: string) => {
        clearError(field);
        setContactInfo((prev) => ({
            ...prev,
            [field]: value,
        }));
    };

    const applyCoupon = async () => {
        if (!couponCode.trim()) return;

        setIsApplyingCoupon(true);

        try {
            await router.post(
                '/cart/apply-coupon',
                {
                    code: couponCode,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setCouponCode('');
                    },
                    onError: (errors) => {
                        console.error('Failed to apply coupon:', errors);
                        addToast({
                            type: 'error',
                            title: t('cart.coupon_invalid'),
                            description: t('cart.coupon_invalid_description'),
                        });
                    },
                    onFinish: () => {
                        setIsApplyingCoupon(false);
                    },
                },
            );
        } catch (error) {
            console.error('Error applying coupon:', error);
            addToast({
                type: 'error',
                title: t('cart.error'),
                description: t('cart.coupon_apply_error'),
            });
            setIsApplyingCoupon(false);
        }
    };

    const handleBackToCart = () => {
        router.get('/cart');
    };

    const onCheckoutResponse = (page: Page<{ url?: string; flash?: SharedData['flash'] }>) => {
        if (page.props?.url) {
            window.location.href = page.props.url;
            return;
        }

        setIsProcessing(false);
        if (!page.props?.flash?.error) {
            addToast({
                type: 'error',
                title: t('cart.checkout_error'),
                description: t('cart.checkout_error_description'),
            });
        }
    };

    const proceedToPayment = (event?: FormEvent) => {
        event?.preventDefault();

        if (isProcessing) return;

        if (belowMinimum) {
            addToast({
                type: 'error',
                title: t('cart.checkout_error'),
                description: t('cart.min_amount_notice', { amount: formatCurrency(minAmount) }),
            });
            return;
        }

        const clientErrors = validate();
        if (Object.keys(clientErrors).length > 0) {
            showFieldErrors(clientErrors);
            return;
        }

        setErrors({});
        setIsProcessing(true);

        const checkoutData = {
            email: contactInfo.email,
            shipping_address: shippingAddress,
            order_notes: orderNotes,
            coupon_code: couponCode,
            marketing_consent: marketingConsent,
            save_address: saveAddress,
        };

        if (auth?.user) {
            router.post('/checkout/cart', checkoutData, {
                onSuccess: onCheckoutResponse,
                onError: handleServerErrors,
            });
        } else {
            router.post('/guest/checkout/cart', checkoutData, {
                onSuccess: onCheckoutResponse,
                onError: handleServerErrors,
            });
        }
    };

    if (!cartItems || cartItems.length === 0) {
        return (
            <>
                <Head title={t('checkout.title')} />

                <div className="min-h-screen bg-white">
                    <Header />

                    <div className="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h1 className="mb-4 text-3xl font-bold text-gray-900">{t('checkout.cart_empty')}</h1>
                            <p className="mb-8 text-lg text-gray-600">{t('checkout.continue_shopping')}</p>

                            <Button asChild size="lg" className="px-8">
                                <Link href="/products">{t('cart.browse_products')}</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`${t('checkout.title')} - ${totalItems} ${totalItems === 1 ? t('cart.item') : t('cart.items')}`} />

            <LoadingOverlay isLoading={isLoading} className={`min-h-screen bg-white ${isMobile ? 'pb-24' : ''}`}>
                <Header />

                <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">

                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-gray-900">{t('checkout.title')}</h1>
                        <p className="mt-2 text-gray-600">
                            {totalItems} {totalItems === 1 ? t('cart.item') : t('cart.items')} {t('checkout.ready_for_checkout')}
                        </p>
                    </div>

                    <Card className="mb-6 border-2 border-gray-700 bg-white shadow-md lg:hidden">
                        <Collapsible open={isSummaryOpen} onOpenChange={setIsSummaryOpen}>
                            <CollapsibleTrigger className="w-full">
                                <CardHeader className="transition-colors hover:bg-gray-50">
                                    <CardTitle className="flex items-center justify-between text-left text-base text-black">
                                        {isSummaryOpen ? t('checkout.summary_hide') : t('checkout.summary_show', { amount: formatCurrency(total) })}
                                        {isSummaryOpen ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                    </CardTitle>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent>
                                    <OrderSummary
                                        lines={cartItems}
                                        subtotal={subtotal}
                                        discountAmount={discountAmount}
                                        discountDescription={discountDescription}
                                        shippingCost={shippingCost}
                                        freeShippingRemaining={freeShippingRemaining}
                                        total={total}
                                        taxAmount={taxAmount}
                                        taxRate={taxRate}
                                    />
                                </CardContent>
                            </CollapsibleContent>
                        </Collapsible>
                    </Card>

                    <div className="lg:grid lg:grid-cols-[minmax(0,1fr)_24rem] lg:items-start lg:gap-8">
                    <form id="checkout-form" noValidate onSubmit={proceedToPayment} className="space-y-6">

                        <Card className="border-2 border-gray-700 bg-white shadow-md">
                            <Collapsible open={isContactOpen} onOpenChange={setIsContactOpen}>
                                <CollapsibleTrigger className="w-full">
                                    <CardHeader className="transition-colors hover:bg-gray-50">
                                        <CardTitle className="flex items-center justify-between text-black">
                                            <div className="flex items-center gap-2">
                                                <CreditCard className="h-5 w-5" />
                                                {t('checkout.contact_information')}
                                            </div>
                                            {isContactOpen ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                        </CardTitle>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent className="space-y-4">
                                        <div>
                                            <Label htmlFor="email" className="font-medium text-black">
                                                {t('checkout.email')} *
                                            </Label>
                                            <Input
                                                id="email"
                                                name="email"
                                                autoComplete="email"
                                                {...invalidProps('email', 'email')}
                                                type="email"
                                                value={contactInfo.email}
                                                onChange={(e) => updateContactInfo('email', e.target.value)}
                                                required
                                                placeholder={t('checkout.email_placeholder')}
                                                className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                            />
                                            <InputError id="email-error" message={errors['email']} className="mt-1" />
                                            <p className="mt-1 text-sm text-gray-600">{t('checkout.email_confirmation_text')}</p>
                                        </div>
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>

                        <Card className="border-2 border-gray-700 bg-white shadow-md">
                            <Collapsible open={isShippingOpen} onOpenChange={setIsShippingOpen}>
                                <CollapsibleTrigger className="w-full">
                                    <CardHeader className="transition-colors hover:bg-gray-50">
                                        <CardTitle className="flex items-center justify-between text-black">
                                            <div className="flex items-center gap-2">
                                                <Truck className="h-5 w-5" />
                                                {t('checkout.shipping_information')}
                                            </div>
                                            {isShippingOpen ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                        </CardTitle>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent className="space-y-4">
                                        {savedAddresses.length > 1 && (
                                            <div>
                                                <Label htmlFor="saved_address" className="font-medium text-black">
                                                    {t('checkout.saved_addresses')}
                                                </Label>
                                                <Select value={selectedAddress} onValueChange={selectSavedAddress}>
                                                    <SelectTrigger id="saved_address" className="border-gray-300 bg-white text-black">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent className="bg-white text-black">
                                                        {savedAddresses.map((address) => (
                                                            <SelectItem key={address.id} value={String(address.id)}>
                                                                {`${address.first_name} ${address.last_name} · ${address.address_line_1}, ${address.city}`}
                                                            </SelectItem>
                                                        ))}
                                                        <SelectItem value="new">{t('checkout.use_new_address')}</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        )}
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div>
                                                <Label htmlFor="first_name" className="font-medium text-black">
                                                    {t('checkout.first_name')} *
                                                </Label>
                                                <Input
                                                    id="first_name"
                                                    name="first_name"
                                                    autoComplete="given-name"
                                                    {...invalidProps('shipping_address.first_name', 'first_name')}
                                                    value={shippingAddress.first_name}
                                                    onChange={(e) => updateShippingAddress('first_name', e.target.value)}
                                                    required
                                                    className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                                />
                                                <InputError id="first_name-error" message={errors['shipping_address.first_name']} className="mt-1" />
                                            </div>
                                            <div>
                                                <Label htmlFor="last_name" className="font-medium text-black">
                                                    {t('checkout.last_name')} *
                                                </Label>
                                                <Input
                                                    id="last_name"
                                                    name="last_name"
                                                    autoComplete="family-name"
                                                    {...invalidProps('shipping_address.last_name', 'last_name')}
                                                    value={shippingAddress.last_name}
                                                    onChange={(e) => updateShippingAddress('last_name', e.target.value)}
                                                    required
                                                    className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                                />
                                                <InputError id="last_name-error" message={errors['shipping_address.last_name']} className="mt-1" />
                                            </div>
                                        </div>

                                        <div>
                                            <Label htmlFor="company" className="text-gray-600">
                                                {t('checkout.company')} ({t('checkout.optional')})
                                            </Label>
                                            <Input
                                                id="company"
                                                name="company"
                                                autoComplete="organization"
                                                {...invalidProps('shipping_address.company', 'company')}
                                                value={shippingAddress.company}
                                                onChange={(e) => updateShippingAddress('company', e.target.value)}
                                                className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                            />
                                            <InputError id="company-error" message={errors['shipping_address.company']} className="mt-1" />
                                        </div>

                                        <div>
                                            <Label htmlFor="address_line_1" className="font-medium text-black">
                                                {t('checkout.address_line_1')} *
                                            </Label>
                                            <Input
                                                id="address_line_1"
                                                name="address_line_1"
                                                autoComplete="address-line1"
                                                {...invalidProps('shipping_address.address_line_1', 'address_line_1')}
                                                value={shippingAddress.address_line_1}
                                                onChange={(e) => updateShippingAddress('address_line_1', e.target.value)}
                                                required
                                                className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                            />
                                            <InputError
                                                id="address_line_1-error"
                                                message={errors['shipping_address.address_line_1']}
                                                className="mt-1"
                                            />
                                        </div>

                                        <div>
                                            <Label htmlFor="address_line_2" className="text-gray-600">
                                                {t('checkout.address_line_2')} ({t('checkout.optional')})
                                            </Label>
                                            <Input
                                                id="address_line_2"
                                                name="address_line_2"
                                                autoComplete="address-line2"
                                                {...invalidProps('shipping_address.address_line_2', 'address_line_2')}
                                                value={shippingAddress.address_line_2}
                                                onChange={(e) => updateShippingAddress('address_line_2', e.target.value)}
                                                className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                            />
                                            <InputError
                                                id="address_line_2-error"
                                                message={errors['shipping_address.address_line_2']}
                                                className="mt-1"
                                            />
                                        </div>

                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                            <div>
                                                <Label htmlFor="city" className="font-medium text-black">
                                                    {t('checkout.city')} *
                                                </Label>
                                                <Input
                                                    id="city"
                                                    name="city"
                                                    autoComplete="address-level2"
                                                    {...invalidProps('shipping_address.city', 'city')}
                                                    value={shippingAddress.city}
                                                    onChange={(e) => updateShippingAddress('city', e.target.value)}
                                                    required
                                                    className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                                />
                                                <InputError id="city-error" message={errors['shipping_address.city']} className="mt-1" />
                                            </div>
                                            <div>
                                                <Label htmlFor="state" className="font-medium text-black">
                                                    {t('checkout.state')} *
                                                </Label>
                                                <StateField
                                                    id="state"
                                                    country={shippingAddress.country}
                                                    provinces={provinces}
                                                    value={shippingAddress.state}
                                                    onChange={(value) => updateShippingAddress('state', value)}
                                                    invalidProps={invalidProps('shipping_address.state', 'state')}
                                                    className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                                />
                                                <InputError id="state-error" message={errors['shipping_address.state']} className="mt-1" />
                                            </div>
                                            <div>
                                                <Label htmlFor="postal_code" className="font-medium text-black">
                                                    {t('checkout.postal_code')} *
                                                </Label>
                                                <Input
                                                    id="postal_code"
                                                    name="postal_code"
                                                    autoComplete="postal-code"
                                                    inputMode="text"
                                                    autoCapitalize="characters"
                                                    {...invalidProps('shipping_address.postal_code', 'postal_code')}
                                                    value={shippingAddress.postal_code}
                                                    onChange={(e) => updatePostalCode(e.target.value)}
                                                    required
                                                    className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                                />
                                                <InputError
                                                    id="postal_code-error"
                                                    message={errors['shipping_address.postal_code']}
                                                    className="mt-1"
                                                />
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div>
                                                <Label htmlFor="country" className="font-medium text-black">
                                                    {t('checkout.country')} *
                                                </Label>
                                                <Select
                                                    name="country"
                                                    autoComplete="country"
                                                    value={shippingAddress.country}
                                                    onValueChange={(value) => updateShippingAddress('country', value)}
                                                >
                                                    <SelectTrigger
                                                        id="country"
                                                        {...invalidProps('shipping_address.country', 'country')}
                                                        className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent className="bg-white text-black">
                                                        {destinations.map((code) => (
                                                            <SelectItem key={code} value={code}>
                                                                {countryName(code)}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <InputError id="country-error" message={errors['shipping_address.country']} className="mt-1" />
                                            </div>
                                            <div>
                                                <Label htmlFor="phone" className="font-medium text-black">
                                                    {t('checkout.phone')} *
                                                </Label>
                                                <Input
                                                    id="phone"
                                                    name="phone"
                                                    type="tel"
                                                    autoComplete="tel"
                                                    {...invalidProps('shipping_address.phone', 'phone')}
                                                    value={shippingAddress.phone}
                                                    onChange={(e) => updateShippingAddress('phone', e.target.value)}
                                                    required
                                                    className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                                />
                                                <InputError id="phone-error" message={errors['shipping_address.phone']} className="mt-1" />
                                            </div>
                                        </div>

                                        {auth?.user && (
                                            <div className="flex items-center gap-2">
                                                <Checkbox id="save_address" checked={saveAddress} onCheckedChange={(checked) => setSaveAddress(checked === true)} />
                                                <Label htmlFor="save_address" className="text-sm text-gray-700">
                                                    {t('checkout.save_address')}
                                                </Label>
                                            </div>
                                        )}
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>

                        <Card className="border-2 border-gray-700 bg-white shadow-md">
                            <Collapsible open={isDiscountOpen} onOpenChange={setIsDiscountOpen}>
                                <CollapsibleTrigger className="w-full">
                                    <CardHeader className="transition-colors hover:bg-gray-50">
                                        <CardTitle className="flex items-center justify-between text-black">
                                            <div className="flex items-center gap-2">
                                                <Tag className="h-5 w-5" />
                                                {t('cart.discount_code')}
                                            </div>
                                            {isDiscountOpen ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                        </CardTitle>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent>
                                        <div className="flex space-x-2">
                                            <Input
                                                type="text"
                                                placeholder={t('cart.coupon_placeholder')}
                                                value={couponCode}
                                                onChange={(e) => setCouponCode(e.target.value)}
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        e.preventDefault();
                                                        applyCoupon();
                                                    }
                                                }}
                                                autoComplete="off"
                                                className="flex-1 border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={applyCoupon}
                                                disabled={!couponCode.trim() || isApplyingCoupon}
                                                className="border-gray-300 hover:bg-gray-50"
                                            >
                                                {isApplyingCoupon ? t('cart.applying') : t('cart.apply')}
                                            </Button>
                                        </div>
                                        {discountAmount > 0 && (
                                            <div className="mt-3 rounded-lg border border-green-200 bg-green-50 p-3">
                                                <p className="text-sm text-green-800">
                                                    {t('cart.discount_applied', { amount: formatCurrency(discountAmount) })}
                                                </p>
                                            </div>
                                        )}
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>

                        <Card className="border-2 border-gray-700 bg-white shadow-md">
                            <Collapsible open={isNotesOpen} onOpenChange={setIsNotesOpen}>
                                <CollapsibleTrigger className="w-full">
                                    <CardHeader className="transition-colors hover:bg-gray-50">
                                        <CardTitle className="flex items-center justify-between text-black">
                                            <div className="flex items-center gap-2">
                                                <FileText className="h-5 w-5" />
                                                {t('checkout.order_notes')} ({t('checkout.optional')})
                                            </div>
                                            {isNotesOpen ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                        </CardTitle>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent>
                                        <Textarea
                                            id="order_notes"
                                            name="order_notes"
                                            {...invalidProps('order_notes', 'order_notes')}
                                            placeholder={t('checkout.order_notes_placeholder')}
                                            value={orderNotes}
                                            onChange={(e) => {
                                                clearError('order_notes');
                                                setOrderNotes(e.target.value);
                                            }}
                                            maxLength={orderNotesMax}
                                            className="resize-none border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                            rows={3}
                                        />
                                        <InputError id="order_notes-error" message={errors['order_notes']} className="mt-1" />
                                        <p className="mt-1 text-xs text-gray-500">
                                            {orderNotes.length}/{orderNotesMax} {t('checkout.characters')}
                                        </p>
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>

                        {belowMinimum && (
                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                <p className="text-sm text-amber-800">{t('cart.min_amount_notice', { amount: formatCurrency(minAmount) })}</p>
                            </div>
                        )}

                        <MarketingConsentCheckbox checked={marketingConsent} onCheckedChange={setMarketingConsent} disabled={isProcessing} />

                        <div className="grid hidden grid-cols-1 gap-4 pt-6 md:grid md:grid-cols-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleBackToCart}
                                size="lg"
                                className="w-full border-gray-300 hover:bg-gray-50"
                            >
                                <ArrowLeft className="mr-2 h-5 w-5" />
                                {t('cart.back_to_cart')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={isProcessing || belowMinimum}
                                size="lg"
                                className="w-full bg-black text-white hover:bg-gray-800"
                            >
                                <CreditCard className="mr-2 h-5 w-5" />
                                {isProcessing ? t('checkout.processing') : t('checkout.pay', { amount: formatCurrency(total) })}
                            </Button>
                        </div>
                    </form>

                    <aside className="hidden lg:sticky lg:top-8 lg:block">
                        <Card className="border-2 border-gray-700 bg-white shadow-md">
                            <CardHeader>
                                <CardTitle className="text-black">{t('cart.order_summary')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <OrderSummary
                                    lines={cartItems}
                                    subtotal={subtotal}
                                    discountAmount={discountAmount}
                                    discountDescription={discountDescription}
                                    shippingCost={shippingCost}
                                    freeShippingRemaining={freeShippingRemaining}
                                    total={total}
                                    taxAmount={taxAmount}
                                    taxRate={taxRate}
                                />
                            </CardContent>
                        </Card>
                    </aside>
                    </div>
                </div>

                {isMobile && (
                    <div className="fixed right-0 bottom-0 left-0 z-50 border-t border-gray-300 bg-white p-4 shadow-lg">
                        <div className="grid grid-cols-2 gap-4">
                            <Button type="button" variant="outline" onClick={handleBackToCart} className="h-12 border-gray-300 hover:bg-gray-50">
                                <ArrowLeft className="mr-2 h-5 w-5" />
                                {t('cart.back')}
                            </Button>
                            <Button
                                type="submit"
                                form="checkout-form"
                                disabled={isProcessing || belowMinimum}
                                className="h-12 bg-black font-semibold text-white hover:bg-gray-800"
                            >
                                <CreditCard className="mr-2 h-5 w-5" />
                                {isProcessing ? t('checkout.processing') : t('checkout.pay', { amount: formatCurrency(total) })}
                            </Button>
                        </div>
                    </div>
                )}
            </LoadingOverlay>
        </>
    );
}