import 'package:matjar_app/features/orders/data/models/created_order.dart';
import 'package:matjar_app/features/orders/data/models/order_details.dart';
import 'package:matjar_app/features/orders/data/models/order_status.dart';
import 'package:matjar_app/features/products/data/models/product.dart';

const Product studioHeadphones = Product(
  slug: 'studio-headphones',
  name: 'سماعة رأس استوديو',
  description: 'سماعة رأس لاسلكية بعزل للضوضاء وبطارية 40 ساعة',
  price: 12000,
  priceFormatted: '12,000 ج.س',
  imageUrl: null,
  colorHex: '#9F1239',
);

const Product proEarbuds = Product(
  slug: 'pro-earbuds',
  name: 'سماعات أذن برو',
  description: 'سماعات أذن صغيرة مع علبة شحن وميكروفون للمكالمات',
  price: 8500,
  priceFormatted: '8,500 ج.س',
  imageUrl: null,
  colorHex: '#B45309',
);

const Product miniSpeaker = Product(
  slug: 'mini-speaker',
  name: 'مكبر صوت صغير',
  description: 'مكبر صوت بلوتوث مقاوم للماء يعمل 12 ساعة',
  price: 21000,
  priceFormatted: '21,000 ج.س',
  imageUrl: null,
  colorHex: '#44403C',
);

Map<String, Object?> productJson({
  String slug = 'studio-headphones',
  Object? price = 12000,
  Object? imageUrl = 'http://localhost:8001/images/studio-headphones.jpg',
}) => {
  'slug': slug,
  'name': 'سماعة رأس استوديو',
  'description': 'سماعة رأس لاسلكية بعزل للضوضاء وبطارية 40 ساعة',
  'price': price,
  'price_formatted': '12,000 ج.س',
  'image_url': imageUrl,
  'color': '#9F1239',
};

Map<String, Object?> createdOrderJson() => {
  'data': {
    'reference': 'MJ-24A7Q',
    'status': 'pending',
    'total': 32500,
    'total_formatted': '32,500 ج.س',
    'payment_url': 'http://localhost:8000/checkout/cs_test_81f2',
    'access_token': 'tok_9c1e5d',
    'success_url_prefix': 'http://localhost:8001/orders/MJ-24A7Q/return',
    'cancel_url_prefix':
        'http://localhost:8001/orders/MJ-24A7Q/return?cancelled=1',
  },
};

Map<String, Object?> orderJson({
  String status = 'paid',
  String? invoice = 'INV-2026-0042',
  String? paidAt = '2026-11-17T09:41:00+03:00',
}) => {
  'data': {
    'reference': 'MJ-24A7Q',
    'status': status,
    'total': 32500,
    'total_formatted': '32,500 ج.س',
    'items': [
      {
        'name': 'سماعة رأس استوديو',
        'quantity': 2,
        'unit_amount': 12000,
        'image_url': 'http://localhost:8001/images/studio-headphones.jpg',
      },
      {
        'name': 'سماعات أذن برو',
        'quantity': 1,
        'unit_amount': 8500,
        'image_url': null,
      },
    ],
    'invoice': invoice,
    'paid_at': paidAt,
    'created_at': '2026-11-17T09:38:12+03:00',
  },
};

CreatedOrder createdOrder() => const CreatedOrder(
  reference: 'MJ-24A7Q',
  status: OrderStatus.pending,
  total: 32500,
  totalFormatted: '32,500 ج.س',
  paymentUrl: 'http://10.0.2.2:8000/checkout/cs_test_81f2',
  accessToken: 'tok_9c1e5d',
  successUrlPrefix: 'http://10.0.2.2:8001/orders/MJ-24A7Q/return',
  cancelUrlPrefix: 'http://10.0.2.2:8001/orders/MJ-24A7Q/return?cancelled=1',
);

OrderDetails orderDetails(OrderStatus status) => OrderDetails(
  reference: 'MJ-24A7Q',
  status: status,
  total: 32500,
  totalFormatted: '32,500 ج.س',
  items: const [],
  invoice: status == OrderStatus.paid ? 'INV-2026-0042' : null,
  paidAt: status == OrderStatus.paid ? DateTime.utc(2026, 11, 17, 6, 41) : null,
  createdAt: DateTime.utc(2026, 11, 17, 6, 38),
);
