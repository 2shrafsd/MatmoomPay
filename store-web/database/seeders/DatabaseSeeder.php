<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Product photos live in public/images/products/<slug>.jpg; their sources are listed in public/images/CREDITS.md.
     * Prices are whole Sudanese pounds.
     */
    public function run(): void
    {
        $products = [
            ['headphones-beige', 'headphones', 'سماعة رأس لاسلكية هادئة — بيج', 'عزل للضوضاء وبطارية تكفي حتى 60 ساعة، ووسائد ناعمة للاستخدام الطويل.', 285_000, '#d8cfc2'],
            ['headphones-anc-black', 'headphones', 'سماعة رأس بعزل الضوضاء — أسود', 'عزل نشط للضوضاء وميكروفونات للمكالمات، وتُطوى في حقيبتها.', 340_000, '#2b2b2b'],
            ['headphones-silver', 'headphones', 'سماعة رأس معدنية — فضي', 'هيكل من الألمنيوم ووسائد من الشبك، صوت واسع وثبات مريح.', 520_000, '#b9bcc2'],
            ['headphones-on-ear-brown', 'headphones', 'سماعة رأس خفيفة — بني', 'سماعة فوق الأذن بوزن خفيف وكابل يُفصل عند الحاجة.', 165_000, '#8a5a3c'],
            ['earbuds-silver', 'earbuds', 'سماعات أذن لاسلكية — فضي', 'صغيرة وخفيفة، مع ثلاثة مقاسات لأطراف السيليكون.', 145_000, '#c9cbcf'],
            ['earbuds-case-white', 'earbuds', 'سماعات أذن مع علبة شحن — أبيض', 'علبة الشحن تعطيك حتى 24 ساعة إضافية من الاستماع.', 98_000, '#e9e7e3'],
            ['speaker-capsule-black', 'speakers', 'مكبر صوت محمول — أسود', 'صغير يدخل الحقيبة، مع منفذ USB ومدخل لبطاقة الذاكرة.', 76_000, '#262626'],
            ['speaker-desk-white', 'speakers', 'مكبر صوت مكتبي — فضي', 'صوت واضح للمكتب، يتصل بالكمبيوتر أو الهاتف.', 190_000, '#cfd0d2'],
            ['desk-microphone', 'accessories', 'ميكروفون مكتبي بحامل ثلاثي', 'ميكروفون USB للاجتماعات والتسجيل، مع حامل وفلتر للصوت.', 120_000, '#3b3b3b'],
            ['headphone-stand-walnut', 'accessories', 'حامل سماعة من خشب الجوز', 'حامل ثقيل يحفظ السماعة ويرتب المكتب.', 45_000, '#6b4a33'],
            ['usb-c-cable', 'accessories', 'كابل شحن USB-C مضفّر، 1 متر', 'كابل متين يدعم الشحن السريع ونقل البيانات.', 9_500, '#a89a86'],
            ['headphones-travel-case', 'accessories', 'حقيبة حمل للسماعة', 'حقيبة صلبة تحمي السماعة في السفر، مع جيب للكابلات.', 28_000, '#333333'],
        ];

        foreach ($products as [$slug, $category, $name, $description, $price, $color]) {
            $image = 'images/products/'.$slug.'.jpg';

            Product::updateOrCreate(['slug' => $slug], compact('category', 'name', 'description', 'price', 'color', 'image'));
        }

        // Products that left the catalogue disappear from the shop; past orders keep their own copy of names and prices.
        Product::whereNotIn('slug', array_column($products, 0))->delete();
    }
}
