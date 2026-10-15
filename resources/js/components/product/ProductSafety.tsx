import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import useTranslation from '@/hooks/useTranslation';
import { usePage } from '@inertiajs/react';

interface Compliance {
    manufacturer_name: string | null;
    manufacturer_address: string | null;
    manufacturer_email: string | null;
    eu_responsible_person: string | null;
}

interface SafetyPageProps {
    compliance?: Compliance;
    product: {
        base_sku?: string | null;
        safety_information?: string | null;
        variants: { id: number; sku?: string | null }[];
    };
    [key: string]: unknown;
}

/**
 * GPSR block of the product page (Regulation (EU) 2023/988): manufacturer with postal and electronic address, the EU
 * responsible person when there is one, the product identifier (the selected variant's SKU, else the base SKU) and the
 * product's safety information. Data from ProductsController::show (`compliance`, `product`).
 */
export default function ProductSafety({ variantId }: { variantId: number }) {
    const { t } = useTranslation();
    const { compliance, product } = usePage<SafetyPageProps>().props;

    const identifier = product.variants.find((variant) => variant.id === variantId)?.sku || product.base_sku || null;
    const manufacturer = [compliance?.manufacturer_name, compliance?.manufacturer_address].filter(Boolean).join('\n');

    return (
        <Accordion type="single" collapsible className="mt-8 rounded-lg border border-gray-300">
            <AccordionItem value="product-safety" className="border-b-0">
                <AccordionTrigger className="rounded-lg bg-gray-50 p-4 text-lg font-semibold text-gray-900 hover:no-underline">
                    {t('product_safety.title')}
                </AccordionTrigger>
                <AccordionContent className="space-y-4 p-4 text-sm text-gray-700">
                    {manufacturer && (
                        <div>
                            <h3 className="font-medium text-gray-900">{t('product_safety.manufacturer')}</h3>
                            <p className="whitespace-pre-line">{manufacturer}</p>
                            {compliance?.manufacturer_email && (
                                <p>
                                    {t('product_safety.email')}:{' '}
                                    <a href={`mailto:${compliance.manufacturer_email}`} className="underline">
                                        {compliance.manufacturer_email}
                                    </a>
                                </p>
                            )}
                        </div>
                    )}

                    {compliance?.eu_responsible_person && (
                        <div>
                            <h3 className="font-medium text-gray-900">{t('product_safety.eu_responsible_person')}</h3>
                            <p className="whitespace-pre-line">{compliance.eu_responsible_person}</p>
                        </div>
                    )}

                    {identifier && (
                        <div>
                            <h3 className="font-medium text-gray-900">{t('product_safety.identifier')}</h3>
                            <p>{identifier}</p>
                        </div>
                    )}

                    {product.safety_information && (
                        <div>
                            <h3 className="font-medium text-gray-900">{t('product_safety.information')}</h3>
                            <p className="whitespace-pre-line">{product.safety_information}</p>
                        </div>
                    )}
                </AccordionContent>
            </AccordionItem>
        </Accordion>
    );
}
