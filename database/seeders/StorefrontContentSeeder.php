<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Collection;
use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentPage;
use App\Domain\Content\Models\SiteContent;
use App\Domain\Fulfillment\Models\ShippingMethod;
use App\Domain\Marketing\Models\Discount;
use Illuminate\Database\Seeder;

final class StorefrontContentSeeder extends Seeder
{
    public function run(): void
    {
        SiteContent::query()->updateOrCreate(['key' => 'store_information'], ['value' => config('raoza.store')]);
        SiteContent::query()->updateOrCreate(['key' => 'demo_notice'], ['value' => ['enabled' => false]]);

        Category::query()->where('slug', 't-shirts')->update([
            'description' => 'Graphic T-shirts selected for clean everyday styling and layered looks.',
            'seo_title' => 'Graphic T-Shirts — RAOZA',
            'seo_description' => 'Shop RAOZA graphic T-shirts and explore the current urban editorial selection.',
        ]);
        Category::query()->where('slug', 'hoodies')->update([
            'description' => 'Printed hoodies that bring RAOZA graphics into colder days and layered outfits.',
            'seo_title' => 'Printed Hoodies — RAOZA',
            'seo_description' => 'Shop RAOZA printed hoodies in the current curated selection.',
        ]);
        Collection::query()->where('slug', 'drop-01')->update([
            'description' => 'The opening RAOZA edit: graphic essentials shaped by the core burgundy, cream and black palette.',
        ]);
        Collection::query()->where('slug', 'core-essentials')->update([
            'description' => 'Clean everyday pieces with restrained graphics and an easy place in a modern wardrobe.',
        ]);

        Product::query()->each(function (Product $product): void {
            $product->update([
                'fit_notes' => $product->fit_notes ?: 'Fit can vary by garment and style. Choose your usual size and contact customer service when you need help comparing options.',
                'product_details' => $product->product_details ?: 'RAOZA artwork is applied to a ready-made garment using heat-transfer production.',
                'care_instructions' => $product->care_instructions ?: 'Follow the garment care label. Wash and iron printed areas with care.',
            ]);
            $product->images()->whereNull('variant_id')->update(['alt_text' => $product->name]);
        });
        Product::query()->where('fit_notes', 'Choose your usual size. Product-specific measurements can be added to this page when available.')->update([
            'fit_notes' => 'Fit can vary by garment and style. Choose your usual size and contact customer service when you need help comparing options.',
        ]);
        Product::query()->where('slug', 'ra-monogram-hoodie')->update([
            'description' => 'A graphic hoodie presentation featuring the RA monogram direction.',
        ]);
        Product::query()->where('slug', 'archive-01-hoodie')->update([
            'description' => 'A neutral hoodie built around a restrained archive-inspired graphic.',
        ]);
        Product::query()->where('slug', 'quiet-signal-hoodie')->update([
            'description' => 'A hoodie with a minimal graphic signal and a clean everyday direction.',
        ]);
        Product::query()->where('slug', 'city-signal-qa-tee')->update([
            'name' => 'City Signal Tee',
            'description' => 'A graphic T-shirt shaped around a clear city-signal motif.',
            'seo_title' => 'City Signal Tee — RAOZA',
            'seo_description' => 'Explore the RAOZA City Signal graphic T-shirt.',
        ]);
        Product::query()->where('slug', 'city-signal-qa-tee')->first()?->images()->update(['alt_text' => 'City Signal Tee']);

        ShippingMethod::query()->updateOrCreate(['code' => 'nl-standard'], [
            'name' => 'Standard delivery',
            'provider' => 'manual',
            'price' => 495,
            'currency' => 'EUR',
            'active' => true,
            'position' => 1,
            'configuration' => ['description' => 'Tracked delivery within the Netherlands. The delivery estimate is confirmed after dispatch.'],
        ]);
        ShippingMethod::query()->where('code', 'nl-free')->update([
            'name' => 'Complimentary delivery',
            'provider' => 'manual',
            'active' => false,
            'configuration' => ['description' => 'Enabled only when a complimentary-delivery offer applies.'],
        ]);
        Discount::query()->where('code', 'WELCOME10')->update(['name' => 'Welcome 10%']);
        Discount::query()->where('code', 'RAOZA5')->update(['name' => 'RAOZA €5']);

        foreach ($this->pages() as $key => $page) {
            ContentPage::query()->updateOrCreate(['key' => $key], [
                ...$page,
                'status' => ContentStatus::Published,
                'indexable' => true,
                'published_at' => now(),
            ]);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function pages(): array
    {
        return [
            'about' => $this->page('About RAOZA', 'about', 'RAOZA is a design-led printed apparel label with a modern, urban and editorial point of view.', [
                ['heading' => 'A focused label', 'body' => 'RAOZA approaches printed apparel as a curated fashion collection: fewer pieces, considered graphics and a consistent visual language. The first market focus is the Netherlands.'],
                ['heading' => 'How the pieces are made', 'body' => 'RAOZA selects ready-made blank garments suitable for printing and applies original artwork through heat-press and heat-transfer production. Garment-specific fit, composition and care information belongs on each product page.'],
                ['heading' => 'Designed to evolve', 'body' => 'Collections are released as focused edits. The aim is a clear wardrobe proposition rather than an endless catalogue: graphic expression, clean silhouettes and pieces that work in everyday rotation.'],
            ], 'About RAOZA — Printed apparel with an editorial point of view'),
            'contact' => $this->page('Contact', 'contact', 'Questions about an order, delivery, sizing or a return? RAOZA customer service is here to help.', [
                ['heading' => 'How to reach us', 'body' => 'Email customer service using the address shown below. Include your order number when your question concerns an existing order, but never send payment credentials or passwords.'],
                ['heading' => 'Order changes and addresses', 'body' => 'Contact us as soon as possible if an address or order detail is incorrect. We will check the current production state, but changes or cancellation cannot be guaranteed once payment is confirmed or production has started.'],
                ['heading' => 'Damaged or incorrect order', 'body' => 'Send your order number, a short description and clear photos of the issue. We will review the order and explain the available next step.'],
            ], 'Contact RAOZA customer service'),
            'faq' => $this->page('Help & FAQ', 'faq', 'Practical answers about ordering, payment, sizing, delivery and returns.', [
                ['heading' => 'How do I place an order?', 'body' => 'Choose an available size and colour, add the piece to your bag and continue through checkout. Prices, stock, discounts, delivery and VAT are verified by the server before the order is created.'],
                ['heading' => 'Which payment methods are available?', 'body' => 'The secure payment screen shows the methods currently offered by the payment provider. An order only moves into production after payment is verified.'],
                ['heading' => 'How should I choose a size?', 'body' => 'Use the fit information on the product page and the size guide. Where measurements are needed, rely on product-specific measurements rather than a universal chart.'],
                ['heading' => 'Where does RAOZA deliver?', 'body' => 'Checkout currently accepts delivery addresses in the Netherlands. Available methods and their authoritative prices are shown during checkout.'],
                ['heading' => 'Will I receive tracking?', 'body' => 'When tracking is available, it is included in the shipment confirmation. Contact customer service if the address is wrong or tracking does not progress as expected.'],
                ['heading' => 'How do returns and refunds work?', 'body' => 'Contact customer service with your order number. Eligible items are reviewed after receipt. Return acceptance and completion of a payment refund are separate steps.'],
                ['heading' => 'Can I change or cancel an order?', 'body' => 'Ask as soon as possible. We will check the order state, but a change or cancellation is not guaranteed after payment or production has started.'],
                ['heading' => 'How do I care for a printed piece?', 'body' => 'Always follow the garment care label and the care notes on the product page. Treat printed areas gently when washing and ironing.'],
            ], 'RAOZA help and frequently asked questions'),
            'shipping' => $this->page('Shipping & Delivery', 'shipping', 'Delivery options are shown transparently during checkout and calculated from the current server-side shipping methods.', [
                ['heading' => 'Delivery area', 'body' => 'RAOZA currently accepts shipping addresses in the Netherlands. If no valid delivery method is available for an order, checkout cannot continue.'],
                ['heading' => 'Costs and timing', 'body' => 'The checkout displays every available method and its current price before the order is created. Processing begins after verified payment. Delivery timing depends on production, dispatch and the carrier; we do not promise an exact arrival date before shipment.'],
                ['heading' => 'Tracking and shipment confirmation', 'body' => 'A shipment confirmation is sent when the order is marked as shipped. When a tracking link is available, it is included in that message.'],
                ['heading' => 'Address problems', 'body' => 'Check the address carefully at checkout. If something is wrong, contact customer service immediately. We can only change details when the current production and shipment state still allows it.'],
            ], 'Shipping and delivery information — RAOZA'),
            'returns' => $this->page('Returns & Refunds', 'returns', 'RAOZA reviews return requests carefully and keeps the physical return and payment refund steps clear.', [
                ['heading' => 'Requesting a return', 'body' => 'Contact customer service with your order number, the item you want to return and the reason. We will review the request and provide instructions when the item is eligible.'],
                ['heading' => 'Item condition', 'body' => 'Keep the item clean, unworn beyond a reasonable fit check and with its original presentation where possible. Eligibility also depends on the item and applicable consumer rights.'],
                ['heading' => 'Receipt and inspection', 'body' => 'A return is not complete when it is first requested or posted. After receipt, the item is inspected and the approved resolution is recorded. Items assessed as resellable may be returned to stock; this does not determine the payment outcome by itself.'],
                ['heading' => 'Refund processing', 'body' => 'An approved refund is submitted to the original payment flow and remains separate from return acceptance. Partial refunds may apply when only part of an order is approved. The refund is complete only after the payment provider confirms it; your bank may need additional time to display the funds.'],
                ['heading' => 'Damaged or incorrect items', 'body' => 'Contact customer service promptly with the order number, a description and clear photos. Do not return the item before receiving instructions.'],
            ], 'Returns and refunds — RAOZA'),
            'privacy' => $this->page('Privacy Policy', 'privacy', 'This policy explains how RAOZA uses personal data to operate the store, fulfil orders and provide customer service.', [
                ['heading' => 'Data we process', 'body' => 'We process details you provide for checkout and support, such as name, email, phone number, billing and shipping addresses, order contents and correspondence. We also keep consent choices, security records and limited operational logs.'],
                ['heading' => 'Why we use it', 'body' => 'Data is used to create and fulfil orders, reserve inventory, process payments and refunds, send transactional messages, prevent misuse, maintain records and answer support requests. Optional analytics or marketing processing occurs only after the corresponding consent.'],
                ['heading' => 'Payments and service providers', 'body' => 'Payment details are handled through the configured payment provider; RAOZA stores provider references and verified payment states rather than full card credentials. Hosting, email, delivery, payment and professional-service providers may process only the data needed for their role.'],
                ['heading' => 'Retention and security', 'body' => 'Records are retained only for operational, accounting, fraud-prevention and legal needs, then deleted or anonymised under the applicable retention policy. Access is restricted and sensitive credentials are kept outside the application source.'],
                ['heading' => 'Your choices and requests', 'body' => 'You can reject optional cookies or change preferences at any time. For access, correction, deletion, restriction or other privacy questions, contact RAOZA using the customer-service details below. Some records may need to be retained where law requires it.'],
            ], 'Privacy policy — RAOZA'),
            'cookies' => $this->page('Cookie Policy', 'cookies', 'RAOZA uses necessary storage for the shop and offers separate choices for analytics and marketing.', [
                ['heading' => 'Necessary', 'body' => 'Necessary cookies and session storage keep the bag, checkout, security and privacy choices working. They are used because the requested store features cannot operate reliably without them.'],
                ['heading' => 'Analytics', 'body' => 'Analytics remains off unless you opt in. When an analytics provider is configured, this category may measure interactions such as product views, adding to the bag, beginning checkout and verified purchases.'],
                ['heading' => 'Marketing', 'body' => 'Marketing remains off unless you opt in. This category is reserved for consent-based marketing measurement if a provider is configured; RAOZA does not claim a vendor that is not active.'],
                ['heading' => 'Change your choice', 'body' => 'Use “Cookie preferences” in the footer to review or change optional categories. Rejecting optional cookies does not prevent ordinary shopping and checkout.'],
            ], 'Cookie policy and privacy choices — RAOZA'),
            'terms' => $this->page('Terms & Conditions', 'terms', 'These terms describe the customer relationship for orders placed through the RAOZA online store.', [
                ['heading' => 'Applicability and products', 'body' => 'These terms apply to purchases from the RAOZA online store. Product pages describe the current item, available options and price. Images and screen colours may vary slightly by display.'],
                ['heading' => 'Prices and ordering', 'body' => 'Consumer prices are shown in euros and include the configured VAT component. Delivery and valid discounts are shown before payment. An order is registered after checkout, but production starts only after payment is verified.'],
                ['heading' => 'Payment and acceptance', 'body' => 'Payment is handled by the configured payment provider. A return URL or order page alone is not proof of payment. RAOZA may decline or cancel an order where payment fails, stock is unavailable, information is clearly incorrect or fulfilment would be unlawful.'],
                ['heading' => 'Shipping', 'body' => 'The available destination, method and price are determined during checkout. Customers are responsible for entering a complete address and contacting support promptly about errors. Delivery estimates are not guaranteed arrival dates.'],
                ['heading' => 'Changes, cancellation and returns', 'body' => 'Requests are assessed against the current payment and production state. Changes cannot be guaranteed after production starts. Returns and refunds follow the published Returns & Refunds information and applicable consumer rights.'],
                ['heading' => 'Intellectual property', 'body' => 'RAOZA names, graphics, photography and site content may not be reproduced or used commercially without permission, except where law allows.'],
                ['heading' => 'Responsibility and contact', 'body' => 'Nothing in these terms excludes rights or liability that cannot lawfully be excluded. Contact customer service promptly when an order issue occurs so it can be reviewed fairly. Store identity and contact details appear below.'],
                ['heading' => 'Changes to these terms', 'body' => 'The terms applicable when an order is placed govern that order. Future updates may apply to later orders and will be published on this page.'],
            ], 'Terms and conditions — RAOZA'),
            'size-guide' => $this->page('Size Guide', 'size-guide', 'Use product-specific fit notes and measurements whenever they are available.', [
                ['heading' => 'Choosing a size', 'body' => 'Start with the fit description on the product page and compare product-specific measurements with a garment you already own. Do not rely on the same letter size fitting identically across every blank garment.'],
                ['heading' => 'Measurements', 'body' => 'Measurements are published per product when established. Lay a similar garment flat and compare like with like; allow for small measurement variation. If a product has no measurement table, contact customer service before ordering.'],
                ['heading' => 'Need help?', 'body' => 'Tell customer service which product and size you are considering. We will use the available product information without making a fit guarantee that the measurements cannot support.'],
            ], 'Size guide — RAOZA'),
        ];
    }

    /** @param list<array{heading:string,body:string}> $sections */
    private function page(string $title, string $slug, string $intro, array $sections, string $description): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'content' => compact('intro', 'sections'),
            'seo_title' => $title.' — RAOZA',
            'seo_description' => $description,
        ];
    }
}
