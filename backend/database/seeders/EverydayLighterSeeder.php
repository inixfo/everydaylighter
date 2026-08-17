<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ContentPage;
use App\Models\FaqCategory;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EverydayLighterSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'customer']);

        // Keep the convenient local test account out of production.
        if (! app()->environment('production')) {
            $admin = User::firstOrCreate(
                ['email' => 'admin@everydaylighter.test'],
                ['name' => 'EverydayLighter Admin', 'password' => Hash::make('password'), 'email_verified_at' => now()]
            );
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        $category = Category::firstOrCreate(
            ['slug' => 'life-systems'],
            ['uuid' => (string) Str::uuid(), 'name' => 'Life Systems', 'sort_order' => 1]
        );

        $product = Product::updateOrCreate(
            ['slug' => 'mental-load-reset'],
            [
                'uuid' => (string) Str::uuid(),
                'category_id' => $category->id,
                'name' => 'The Mental Load Reset',
                'product_type' => 'ebook',
                'short_description' => 'A practical 30-day system for overwhelmed women and moms who are tired of carrying the whole family in their head.',
                'description' => 'A research-informed guide to reveal invisible responsibilities, externalize remembering, simplify decisions, establish ownership and transfer the load.',
                'status' => 'published',
                'regular_price_minor' => 2900,
                'sale_price_minor' => 1900,
                'currency' => 'USD',
                'featured' => true,
                'published_at' => now(),
            ]
        );

        $tags = collect(['mental load', 'motherhood', 'organization', 'self care'])->map(
            fn ($tag) => Tag::firstOrCreate(['slug' => Str::slug($tag)], ['name' => ucwords($tag)])->id
        );
        $product->tags()->sync($tags);

        $seedPdf = database_path('seed-assets/The_Mental_Load_Reset.pdf');
        if (is_file($seedPdf)) {
            $storagePath = 'products/mental-load-reset/The_Mental_Load_Reset.pdf';
            Storage::disk('private')->put($storagePath, file_get_contents($seedPdf));
            $product->files()->updateOrCreate(
                ['name' => 'The Mental Load Reset.pdf'],
                [
                    'uuid' => (string) Str::uuid(),
                    'file_type' => 'PDF',
                    'file_size_bytes' => filesize($seedPdf),
                    'storage_disk' => 'private',
                    'storage_path' => $storagePath,
                    'version' => '1.0.0',
                    'status' => 'active',
                ]
            );
        }

        foreach ([
            ['general', 'site_name', 'EverydayLighter'],
            ['general', 'timezone', 'UTC'],
        ] as [$group, $key, $value]) {
            DB::table('settings')->updateOrInsert(
                ['group' => $group, 'key' => $key],
                ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->seedContentPages();
        $this->seedFaq();
    }

    private function seedContentPages(): void
    {
        $pages = [
            'about' => [
                'title' => 'About EverydayLighter',
                'meta_title' => 'About EverydayLighter',
                'meta_description' => 'Practical digital tools designed to make everyday life feel lighter.',
                'content' => <<<'MD'
# Practical tools for a life that feels lighter.

EverydayLighter creates thoughtful digital guides and practical systems for women who want less mental clutter, calmer routines, and more room for themselves.

Our products are designed to be clear, useful, and easy to put into practice. We focus on helping you turn an overwhelming problem into a smaller set of decisions, routines, and next steps you can actually use.

## Our approach

**Practical over perfect.** Useful systems beat complicated systems.

**Clear over cluttered.** We aim to make information easier to understand and apply.

**Supportive, not prescriptive.** Our products are educational resources, not a replacement for professional medical, mental-health, legal, financial, or relationship advice.
MD,
            ],
            'terms' => [
                'title' => 'Terms of Use',
                'meta_title' => 'Terms of Use | EverydayLighter',
                'meta_description' => 'Terms that govern use of EverydayLighter and its digital products.',
                'content' => <<<'MD'
# Terms of Use

These Terms govern your use of EverydayLighter, including our website, accounts, digital products, downloads, and related services.

## Digital products

Unless a product page says otherwise, products sold by EverydayLighter are digital products. No physical shipment is included.

A completed purchase gives the purchaser a limited, personal, non-transferable right to use the purchased product. It does not transfer ownership of the underlying intellectual property.

You may not redistribute, resell, publicly upload, or share paid files or purchased access unless the product explicitly includes permission to do so.

## Educational information

EverydayLighter products are provided for educational and informational purposes. Results vary by person and circumstance. Nothing on the site or in a product should be treated as medical, mental-health, legal, financial, or other regulated professional advice.

## Accounts and access

You are responsible for providing accurate information and keeping your account credentials secure. We may restrict abusive, fraudulent, or unauthorized use of the service.

## Payments and refunds

Payments are processed by third-party payment providers. Refund requests are handled according to the Refund Policy and any consumer rights that apply to the purchase.

## Changes

We may update products, site features, and these Terms when needed. The version published on this page is the current version.

## Contact

For questions about these Terms, contact us through the Contact page.
MD,
            ],
            'privacy' => [
                'title' => 'Privacy Policy',
                'meta_title' => 'Privacy Policy | EverydayLighter',
                'meta_description' => 'How EverydayLighter handles account, purchase, support, and website information.',
                'content' => <<<'MD'
# Privacy Policy

EverydayLighter may collect information you provide when you create an account, make a purchase, request support, or use the website. This can include your name, email address, order information, and technical information needed to operate and secure the service.

## Payments

Payment details are processed by our payment provider. EverydayLighter does not store complete card details on its own servers.

## How information is used

We use information to process purchases, deliver digital products, manage accounts, provide support, prevent abuse, improve the service, and meet applicable business or legal obligations.

## Service providers

We may use service providers for payment processing, email delivery, hosting, analytics, security, and similar operational functions. They receive information only as needed to provide those services under their own terms and privacy obligations.

## Marketing and analytics

Where enabled, analytics or advertising technologies may be used in accordance with the settings and consent requirements configured for the site.

## Your choices

You may contact us to ask questions about information associated with your account or purchases. Some records may need to be retained for security, fraud prevention, accounting, tax, or legal reasons.

## Contact

Privacy questions can be submitted through the Contact page.
MD,
            ],
            'refund-policy' => [
                'title' => 'Refund Policy',
                'meta_title' => 'Refund Policy | EverydayLighter',
                'meta_description' => 'EverydayLighter refund policy for digital products.',
                'content' => <<<'MD'
# Refund Policy

EverydayLighter sells digital products that can normally be accessed shortly after payment is confirmed.

If there is a problem with your purchase, contact support with your order reference and the email used at checkout. We will review issues such as duplicate charges, payment errors, corrupted or inaccessible files, or a product that was materially different from what was described on the sales page.

Because digital products can be delivered immediately, a change of mind after successful access or download may not automatically qualify for a refund. Any non-waivable consumer rights that apply to your purchase remain unaffected.

Approved refunds are returned through the original payment method when supported by the payment provider.
MD,
            ],
        ];

        foreach ($pages as $slug => $data) {
            $page = ContentPage::firstOrNew(['slug' => $slug]);
            if (! $page->exists) {
                $page->uuid = (string) Str::uuid();
            }
            $page->fill($data + ['slug' => $slug, 'status' => 'published']);
            $page->save();
        }
    }

    private function seedFaq(): void
    {
        $data = [
            'purchasing' => [
                'name' => 'Purchasing',
                'items' => [
                    ['Do I need an account to buy?', 'No. Eligible products can be purchased as a guest. Creating an account makes it easier to keep purchases together in your library.'],
                    ['When do I get access?', 'Access is normally available after Stripe confirms the payment and EverydayLighter verifies the order.'],
                ],
            ],
            'downloads' => [
                'name' => 'Downloads',
                'items' => [
                    ['How do I access my purchase?', 'Use the secure link shown after payment or the link sent to the email used at checkout. Signed-in customers can also use their library.'],
                    ['Can I share the PDF?', 'Unless a product says otherwise, paid files are licensed for the purchaser and should not be redistributed or resold.'],
                ],
            ],
            'payments' => [
                'name' => 'Payments',
                'items' => [
                    ['How are payments processed?', 'Payments are completed through Stripe. EverydayLighter does not store complete card details on its own servers.'],
                    ['What if I was charged but access is missing?', 'Contact support with the order email and any Stripe receipt or order reference. Avoid paying repeatedly for the same order.'],
                ],
            ],
            'refunds' => [
                'name' => 'Refunds',
                'items' => [
                    ['Can I request a refund?', 'Refund requests are reviewed under the Refund Policy and any applicable consumer rights.'],
                    ['What if I paid twice?', 'Contact support with both payment or order references so the duplicate charge can be reviewed.'],
                ],
            ],
        ];

        foreach ($data as $slug => $categoryData) {
            $category = FaqCategory::updateOrCreate(
                ['slug' => $slug],
                ['name' => $categoryData['name'], 'sort_order' => array_search($slug, array_keys($data), true) + 1, 'status' => 'active']
            );

            foreach ($categoryData['items'] as $index => [$question, $answer]) {
                $category->items()->updateOrCreate(
                    ['question' => $question],
                    ['answer' => $answer, 'sort_order' => $index + 1, 'status' => 'active']
                );
            }
        }
    }
}
