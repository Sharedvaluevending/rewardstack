<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, computed } from 'vue';

defineOptions({
    layout: MainLayout,
});

const selectedCategory = ref('all');

const categories = [
    { id: 'all', name: 'All Ideas', icon: '✨' },
    { id: 'restaurant', name: 'Restaurants & Cafes', icon: '🍕' },
    { id: 'retail', name: 'Retail & Shopping', icon: '🛍️' },
    { id: 'salon', name: 'Salons & Spas', icon: '💅' },
    { id: 'fitness', name: 'Fitness & Gyms', icon: '💪' },
    { id: 'entertainment', name: 'Entertainment', icon: '🎮' },
    { id: 'services', name: 'Services', icon: '🔧' },
    { id: 'automotive', name: 'Automotive', icon: '🚗' },
    { id: 'hospitality', name: 'Hotels & Travel', icon: '🏨' },
    { id: 'healthcare', name: 'Healthcare', icon: '⚕️' },
    { id: 'education', name: 'Education', icon: '📚' },
    { id: 'events', name: 'Events & Venues', icon: '🎪' },
];

const ideas = [
    // ═══════════════════════════════════════
    // RESTAURANT & CAFE IDEAS
    // ═══════════════════════════════════════
    {
        category: 'restaurant',
        title: 'Receipt QR Code',
        description: 'Add a QR code to every receipt that links to a spin-to-win game. Customers can win discounts on their next visit.',
        implementation: 'Print QR codes on receipts automatically. Scan to play = free dessert, 10% off, or a punch card stamp.',
        icon: '🧾',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'restaurant',
        title: 'Table Tent Game Station',
        description: 'Place QR code tent cards on every table. While waiting for food, customers play a game to win a free dessert or appetizer.',
        implementation: 'Create table tents with your QR game code. Set rewards like "Win a free brownie!" to keep customers entertained.',
        icon: '🎯',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'restaurant',
        title: 'Pizza Box Top QR',
        description: 'Add a dynamic QR code to the top of every pizza box. Customers scan at home for exclusive deals on their next order.',
        implementation: 'Print QR codes on box tops linking to a game or instant coupon. "Scan to save 15% on your next pizza!"',
        icon: '🍕',
        difficulty: 'Easy',
        impact: 'Very High',
    },
    {
        category: 'restaurant',
        title: 'Coffee Cup Sleeve QR',
        description: 'Print QR code stickers on coffee cup sleeves. Morning customers scan for loyalty punches or play a quick game.',
        implementation: 'Download your QR code and order sleeve stickers from any print shop, or use our Print Kit. Link to a punch card - every 10 coffees = 1 free!',
        icon: '☕',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'restaurant',
        title: 'Menu QR Games',
        description: 'Add small QR codes next to menu items. Scan to play a game and potentially win that item free!',
        implementation: 'Add QR codes to your menu near popular items. "Scan to win this appetizer FREE!"',
        icon: '📋',
        difficulty: 'Medium',
        impact: 'High',
    },
    {
        category: 'restaurant',
        title: 'Takeout Bag Sticker',
        description: 'Stick QR codes on takeout bags. Customers scan after their meal for a discount on their next order.',
        implementation: 'Print QR stickers for bags. Link to a percentage-off promotion. "Scan for 15% off your next order!"',
        icon: '🥡',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'restaurant',
        title: 'Happy Hour Table Cards',
        description: 'Special QR codes only available during happy hour. Scan to unlock extra discounts or play a quick game.',
        implementation: 'Create a Happy Hour promotion with time-limited rules (e.g. active only 4-7pm). Place QR cards on tables during those hours.',
        icon: '🍻',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'restaurant',
        title: 'Kids Menu Game Zone',
        description: 'Add QR codes to kids menus that launch age-appropriate games. Winners get a free kids meal next visit.',
        implementation: 'Create a kid-friendly QRcade game linked to QR on placemats. Parents love the distraction!',
        icon: '🧸',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'restaurant',
        title: 'Food Truck Trailer QR',
        description: 'Large QR code on your food truck or trailer. Passersby scan even when closed to see deals and claim coupons.',
        implementation: 'Vinyl QR wrap on truck. Links to a promotion. "Show this for 10% off when we\'re open!"',
        icon: '🚚',
        difficulty: 'Medium',
        impact: 'High',
    },
    {
        category: 'restaurant',
        title: 'BOGO Bring-a-Friend Card',
        description: 'Hand customers a QR card with a buy-one-get-one promotion. They bring a friend next time and both benefit.',
        implementation: 'Create a BOGO promotion and print QR cards. "Bring a friend! Buy one entree, get one free." Track redemptions in your dashboard.',
        icon: '👯',
        difficulty: 'Easy',
        impact: 'Very High',
    },
    {
        category: 'restaurant',
        title: 'Napkin Holder QR',
        description: 'QR codes on napkin dispensers. Customers scan while eating for surprise rewards.',
        implementation: 'Custom napkin holder inserts with QR. "Scan for a chance to win free appetizers!"',
        icon: '🧻',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'restaurant',
        title: 'Check Presenter Game',
        description: 'Add QR codes to check presenters. While waiting to pay, customers play to win discounts on current bill.',
        implementation: 'QR sticker in check folder. "Scan to spin for up to 25% off TODAY\'S bill!"',
        icon: '💳',
        difficulty: 'Easy',
        impact: 'Very High',
    },
    {
        category: 'restaurant',
        title: 'Doggy Bag Surprise',
        description: 'QR codes on to-go containers. Scan tomorrow to get a "leftover bonus" discount.',
        implementation: 'Stickers on containers. "Finished your leftovers? Scan for 15% off your next meal!"',
        icon: '📦',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'restaurant',
        title: 'Restroom Mirror Ad',
        description: 'QR code decal on bathroom mirrors. Captive audience = high scan rates.',
        implementation: 'Mirror cling with QR. "Wash hands, scan code, win rewards. That simple."',
        icon: '🪞',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'restaurant',
        title: 'Cross-Promo Neighbor Deal',
        description: 'Partner with a nearby business using cross-promotions. Customers scan one QR to unlock deals at both locations.',
        implementation: 'Set up a cross-promotion with the business next door. Your customers get 10% off there, their customers get 10% off here. Both businesses grow!',
        icon: '🤝',
        difficulty: 'Easy',
        impact: 'Very High',
    },
    {
        category: 'restaurant',
        title: 'Mystery Prize Game Stand',
        description: 'QR stand at the counter linked to a QRcade game with mystery rewards. Every customer gets a surprise.',
        implementation: 'Create a QRcade game with mystery reward type. "Play for a mystery prize - could be free dessert, 25% off, or a free meal!"',
        icon: '🎁',
        difficulty: 'Easy',
        impact: 'High',
    },

    // ═══════════════════════════════════════
    // RETAIL & SHOPPING IDEAS
    // ═══════════════════════════════════════
    {
        category: 'retail',
        title: 'Shopping Bag QR',
        description: 'Print QR codes on shopping bags. Customers scan for an exclusive next-visit deal.',
        implementation: 'Custom bags with QR. "Loved shopping with us? Scan for 20% off your next visit!"',
        icon: '🛍️',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'retail',
        title: 'Price Tag Games',
        description: 'Add tiny QR codes to price tags. Scan to potentially win that item at a discount.',
        implementation: 'QR on select items. "Scan to see if you won up to 50% off this item!"',
        icon: '🏷️',
        difficulty: 'Medium',
        impact: 'Very High',
    },
    {
        category: 'retail',
        title: 'Fitting Room Mirror',
        description: 'QR codes in fitting rooms. Scan while trying on clothes for a discount.',
        implementation: 'Mirror decals in fitting rooms. "Love how you look? Scan for 15% off!"',
        icon: '👗',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'retail',
        title: 'Coupon Booklet Builder',
        description: 'Create a QR code coupon booklet customers can pick up. Each page has a different scannable deal.',
        implementation: 'Print a booklet with 10 different QR codes, each linked to a unique promotion. Worth $50+ in savings. Great gift item!',
        icon: '📕',
        difficulty: 'Medium',
        impact: 'Very High',
    },
    {
        category: 'retail',
        title: 'Loyalty Card Upgrade',
        description: 'Replace paper punch cards with a single QR code card. Scan each visit for digital punches + bonus games.',
        implementation: 'Create a punch card promotion and print QR loyalty cards. Each scan = digital punch + chance to win instant prizes.',
        icon: '💎',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'retail',
        title: 'Window Display QR',
        description: 'Large QR codes in window displays. After-hours shoppers scan to save on their next visit.',
        implementation: 'Window cling QRs near mannequins. "Shop this look! Scan for 10% off."',
        icon: '🪟',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'retail',
        title: 'Gift Receipt Rewards',
        description: 'QR codes on gift receipts. Gift recipients scan for a welcome discount.',
        implementation: 'Use a First-Time Customer promotion linked to QR. "Welcome! Scan to claim your $10 first-visit discount!"',
        icon: '🎁',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'retail',
        title: 'Product Packaging Insert',
        description: 'Include QR cards inside product packaging. Customers find surprises when they open purchases.',
        implementation: 'Print cards for inside boxes. "Thanks for your purchase! Scan for a secret reward."',
        icon: '📬',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'retail',
        title: 'Checkout Counter Game',
        description: 'QR stand at checkout. While waiting in line, scan to play a game and win discounts.',
        implementation: 'Acrylic QR stand at register. "Play while you wait - win up to 30% off!"',
        icon: '🎮',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'retail',
        title: 'VIP Thank-You Card',
        description: 'Give loyal customers QR cards for a special thank-you discount. Reward your best shoppers with exclusive deals.',
        implementation: 'Create a premium promotion with limited redemptions. Print QR cards for top customers. "You\'re a VIP! Scan for your exclusive 25% off."',
        icon: '⭐',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'retail',
        title: 'Returns Counter Retention',
        description: 'When processing returns, offer QR code for a "sorry to see you go" discount.',
        implementation: 'QR card with returns. "We hope to see you again - here\'s 20% off next time."',
        icon: '🔄',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'retail',
        title: 'Stackable Savings Wall',
        description: 'Display multiple QR codes side by side on a wall or stand. Customers scan all of them to stack promotions for maximum savings.',
        implementation: 'Create a stackable promotion pool and add several deals. Customers scan the QR to see all available promotions they can combine.',
        icon: '📊',
        difficulty: 'Medium',
        impact: 'Very High',
    },
    {
        category: 'retail',
        title: 'Merch Ambassador Program',
        description: 'Put QR codes on branded t-shirts or tote bags. When friends scan the QR, the ambassador earns referral rewards.',
        implementation: 'Create a Merch Referral QR code and put it on branded merchandise. After enough friend scans, the ambassador gets a free item or discount.',
        icon: '👕',
        difficulty: 'Medium',
        impact: 'High',
    },
    {
        category: 'retail',
        title: 'Tiered Spending Reward',
        description: 'QR code at checkout linked to a tiered discount. The more customers spend, the bigger the discount they unlock.',
        implementation: 'Create a Tiered Discount promotion. Spend $50 = 10% off, $100 = 15% off, $200 = 25% off. Place QR at register.',
        icon: '📈',
        difficulty: 'Easy',
        impact: 'High',
    },

    // ═══════════════════════════════════════
    // SALON & SPA IDEAS
    // ═══════════════════════════════════════
    {
        category: 'salon',
        title: 'Mirror Station QR',
        description: 'QR codes at each salon station. Clients scan for a next-visit discount.',
        implementation: 'Small QR at each mirror. "Love your look? Scan for 15% off your next visit!"',
        icon: '💇',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'salon',
        title: 'Product Recommendation QR',
        description: 'QR codes on retail products. Scan for a related product discount.',
        implementation: 'Product shelf talkers with QR. "Scan for 10% off your next product purchase!"',
        icon: '🧴',
        difficulty: 'Medium',
        impact: 'Medium',
    },
    {
        category: 'salon',
        title: 'Waiting Area Entertainment',
        description: 'QR codes in waiting area for mini-games while clients wait. Winners get free add-on services.',
        implementation: 'QR poster in waiting room. "Play while you wait - win a free deep conditioning!"',
        icon: '⏰',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'salon',
        title: 'Gift Card Envelope QR',
        description: 'QR codes on gift card envelopes. Recipients scan for a bonus discount.',
        implementation: 'Gift card sleeve with QR. "Lucky you! Scan for an extra $10 off your next visit."',
        icon: '🎀',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'salon',
        title: 'Service Menu QR Games',
        description: 'QR codes next to services on menu board. Scan to potentially win that service free.',
        implementation: 'QR by each service. "Feeling lucky? Scan to win a FREE manicure!"',
        icon: '💅',
        difficulty: 'Medium',
        impact: 'High',
    },
    {
        category: 'salon',
        title: 'First-Time Client Welcome',
        description: 'QR code at the entrance specifically for new clients. First-time customer promotion gives an instant welcome discount.',
        implementation: 'Create a First-Time Customer promotion. Place QR at reception. "New here? Scan for 20% off your first service!"',
        icon: '👋',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'salon',
        title: 'Visit Streak Punch Card',
        description: 'QR code at reception linked to a digital punch card. Every visit earns a punch toward a free service.',
        implementation: 'Create a punch card promotion (e.g. 8 punches = free blowout). QR at front desk. Clients scan each visit to collect punches.',
        icon: '💎',
        difficulty: 'Easy',
        impact: 'Very High',
    },
    {
        category: 'salon',
        title: 'Referral Reward Card',
        description: 'Give clients a QR card to share with friends. When friends scan and visit, both get rewards through the merch referral system.',
        implementation: 'Create a Merch Referral QR code and print cards. "Share this with a friend - when they visit, you BOTH get 15% off!"',
        icon: '💌',
        difficulty: 'Easy',
        impact: 'Very High',
    },

    // ═══════════════════════════════════════
    // FITNESS & GYM IDEAS
    // ═══════════════════════════════════════
    {
        category: 'fitness',
        title: 'Equipment Station QR',
        description: 'QR codes on each piece of gym equipment. Scan after workout for a reward or game.',
        implementation: 'Weatherproof QR stickers on machines. "Crushed it! Scan to play a game and win a free protein shake!"',
        icon: '🏋️',
        difficulty: 'Medium',
        impact: 'High',
    },
    {
        category: 'fitness',
        title: 'Hydration Station Punch Card',
        description: 'QR code by water fountains linked to a digital punch card. Each scan earns a punch toward a free reward.',
        implementation: 'Create a punch card promotion and place QR at water stations. "Scan each refill! 10 refills = free smoothie at the juice bar."',
        icon: '💧',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'fitness',
        title: 'Locker Room Mirror QR',
        description: 'QR codes in locker room. Post-workout scans for discounts or referral bonuses.',
        implementation: 'Mirror decals in locker rooms. "Great workout! Scan for 10% off your next month or refer a friend for a free week."',
        icon: '🚿',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'fitness',
        title: 'Class Check-in QR',
        description: 'Scan QR when entering fitness classes. Build a digital punch card streak for free classes.',
        implementation: 'Create a punch card QR at class entrance. "Class checked in! 10 classes = 1 FREE class."',
        icon: '🧘',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'fitness',
        title: 'Snack Bar Cross-Promo',
        description: 'Partner with your gym snack bar or juice vendor using cross-promotions. Gym members scan for deals at the snack bar and vice versa.',
        implementation: 'Set up a cross-promotion between gym and snack vendor. "Post-workout fuel! Scan for 20% off smoothies + gym members get bonus XP."',
        icon: '🍫',
        difficulty: 'Medium',
        impact: 'Medium',
    },
    {
        category: 'fitness',
        title: 'Personal Training Promo Stand',
        description: 'QR stand near personal trainers. Scan to win a free training session.',
        implementation: 'Standee near PT area. "Want to try personal training? Scan to win a FREE session!"',
        icon: '🎯',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'fitness',
        title: 'Membership Card QR',
        description: 'QR codes on gym membership cards. Scan daily for check-in rewards and games.',
        implementation: 'QR integrated into member cards. Daily scan = QRcade game play. Win a free shake or class pass!',
        icon: '🎟️',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'fitness',
        title: 'Leaderboard Fitness Challenge',
        description: 'Monthly leaderboard game competition at your gym. Members scan the QR to play a QRcade game and compete for the top spot.',
        implementation: 'Set up a QRcade leaderboard QR with monthly reset. "January Challenge - Play to top the leaderboard! #1 wins a free month!"',
        icon: '🏆',
        difficulty: 'Medium',
        impact: 'Very High',
    },

    // ═══════════════════════════════════════
    // ENTERTAINMENT IDEAS
    // ═══════════════════════════════════════
    {
        category: 'entertainment',
        title: 'Movie Ticket Stub QR',
        description: 'QR codes on ticket stubs. Scan after the movie for discounts on concessions next visit.',
        implementation: 'QR printed on stubs. "Enjoyed the show? Scan for free popcorn upgrade next time!"',
        icon: '🎬',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'entertainment',
        title: 'Arcade Entrance Game',
        description: 'QR code at the arcade entrance. Customers play a QRcade game before entering to win bonus deals.',
        implementation: 'QR stand at entrance. "Scan to play before you play! Win a free game token coupon or 20% off your purchase."',
        icon: '🎰',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'entertainment',
        title: 'Bowling Alley Game QR',
        description: 'QR codes at each bowling lane. Scan after your game to play a QRcade game and win discounts on your next visit.',
        implementation: 'QR at each lane. "Finished your game? Scan to play for a chance to win a free game next time!"',
        icon: '🎳',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'entertainment',
        title: 'Mini Golf Reward QR',
        description: 'QR codes at the final hole of mini golf. Scan after your round to claim a reward or play a bonus game.',
        implementation: 'QR at hole 18. "Great round! Scan to play for a free return visit or 25% off your next game!"',
        icon: '⛳',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'entertainment',
        title: 'Escape Room Completion',
        description: 'QR code in final escape room puzzle. Scan when you escape for rewards.',
        implementation: 'Hidden QR in final room. "You escaped! Scan for 25% off your next adventure."',
        icon: '🔐',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'entertainment',
        title: 'Bar Leaderboard Night',
        description: 'QR code at the bar linked to a weekly QRcade leaderboard game. Top players each week win free drinks or tabs.',
        implementation: 'Create a leaderboard QR with weekly reset. "Game Night! Top 3 on the leaderboard win a free round. Play now!"',
        icon: '🧠',
        difficulty: 'Medium',
        impact: 'High',
    },
    {
        category: 'entertainment',
        title: 'Concert Venue QR',
        description: 'QR codes placed around the concert venue. Fans scan for exclusive merch discounts or promotions.',
        implementation: 'Place QR stickers around the venue. "Fan perk! Scan for 20% off merch tonight only."',
        icon: '🎸',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'entertainment',
        title: 'Laser Tag Score QR',
        description: 'QR on laser tag score printouts. Players scan for post-game rewards.',
        implementation: 'QR on score sheets. "Great game! Scan for 15% off your next match or play a bonus game."',
        icon: '🔫',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'entertainment',
        title: 'Leaderboard Game Night',
        description: 'Host a weekly or monthly QRcade leaderboard competition at your venue. Top players win big prizes.',
        implementation: 'Create a leaderboard QR with weekly/monthly reset. Promote it with signage. Top 3 players win prizes like free visits or gift cards.',
        icon: '🏅',
        difficulty: 'Medium',
        impact: 'Very High',
    },

    // ═══════════════════════════════════════
    // SERVICES IDEAS
    // ═══════════════════════════════════════
    {
        category: 'services',
        title: 'Business Card Game QR',
        description: 'Add QR codes to business cards. Prospects scan to play a game and win a service discount.',
        implementation: 'QR on business cards. "Scan to see what discount you\'ve won - up to 30% off!"',
        icon: '💼',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'services',
        title: 'Invoice QR Rewards',
        description: 'QR codes on invoices. Customers who pay scan for rewards or referral bonuses.',
        implementation: 'QR on invoices. "Thanks for paying! Scan for $25 referral bonus offer."',
        icon: '📄',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'services',
        title: 'Service Vehicle Wrap QR',
        description: 'Large QR codes on service vehicles. Drive-by advertising that captures leads with a discount offer.',
        implementation: 'QR on vans/trucks. Link to a promotion or QRcade game. "Need our services? Scan for $50 off your first job!"',
        icon: '🚐',
        difficulty: 'Medium',
        impact: 'Very High',
    },
    {
        category: 'services',
        title: 'Door Hanger QR',
        description: 'QR codes on door hangers after service visits. Neighbors scan for special offers.',
        implementation: 'Leave door hangers on neighbors. "Your neighbor chose us! Scan for 15% off."',
        icon: '🚪',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'services',
        title: 'Completed Job Sticker',
        description: 'Leave a QR sticker after completing work. Future visitors scan for a discount on their own service.',
        implementation: 'Discreet QR sticker near completed work. "Service by [Company] - scan for 15% off your first job with us!"',
        icon: '✅',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'services',
        title: 'Quote Follow-up Card',
        description: 'Leave QR cards after giving quotes. Potential customers scan for time-limited discount.',
        implementation: 'Card with QR. "Still thinking? Scan for 10% off if you book this week!"',
        icon: '💭',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'services',
        title: 'Thank-You Reward Card',
        description: 'After completing a service, hand customers a QR card with a discount for their next booking.',
        implementation: 'Card with QR linked to a promotion. "Thanks for choosing us! Scan for 20% off your next service."',
        icon: '⭐',
        difficulty: 'Easy',
        impact: 'Very High',
    },
    {
        category: 'services',
        title: 'Cross-Promo Partner Card',
        description: 'Create a cross-promotion with a complementary business. Both of you share customers and grow together.',
        implementation: 'Set up a cross-promotion: plumber + electrician, painter + realtor, etc. Each business offers a deal to the other\'s customers.',
        icon: '🤝',
        difficulty: 'Easy',
        impact: 'Very High',
    },
    {
        category: 'services',
        title: 'Repeat Service Punch Card',
        description: 'QR linked to a digital punch card for recurring services. Customers earn a free service after enough visits.',
        implementation: 'Create a punch card promotion. "Every 5th lawn mowing is FREE! Scan after each service to collect your punch."',
        icon: '🔁',
        difficulty: 'Easy',
        impact: 'High',
    },

    // ═══════════════════════════════════════
    // AUTOMOTIVE IDEAS
    // ═══════════════════════════════════════
    {
        category: 'automotive',
        title: 'Oil Change Reminder Sticker',
        description: 'QR codes on windshield stickers. Customers scan when they\'re due for service to claim a discount.',
        implementation: 'Windshield QR sticker linked to a promotion. "Due for service? Scan to save 15% on your next oil change!"',
        icon: '🛢️',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'automotive',
        title: 'Waiting Room Entertainment',
        description: 'QR codes in waiting room for games. Win free car washes or service discounts.',
        implementation: 'QR posters in waiting area. "Play while you wait - win a FREE detail!"',
        icon: '🎮',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'automotive',
        title: 'Car Wash Exit QR',
        description: 'QR code at car wash exit. Scan for upgrade discounts.',
        implementation: 'Sign at exit with QR. "Love that clean feeling? Scan for unlimited wash deal!"',
        icon: '🚿',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'automotive',
        title: 'Loaner Car QR',
        description: 'QR codes in loaner vehicles. Customers scan for special offers while car is in shop.',
        implementation: 'QR on loaner dash. "Enjoying your loaner? Scan for 20% off current service."',
        icon: '🚙',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'automotive',
        title: 'Tire Tread Check Card',
        description: 'QR card given during inspections. Scan for a discount on new tires or next service.',
        implementation: 'Card with QR after inspection linked to a promotion. "Your tires need attention - scan for 15% off a new set."',
        icon: '🛞',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'automotive',
        title: 'Parking Lot Sign QR',
        description: 'QR on customer parking signs. Scan while parked to browse deals.',
        implementation: 'Parking sign QR. "While you\'re here - scan to see today\'s specials!"',
        icon: '🅿️',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'automotive',
        title: 'Key Tag QR',
        description: 'QR code key tags given to customers. Easy access to promotions anytime.',
        implementation: 'Branded key tag with QR linked to your latest promotion. "Scan anytime for service discounts."',
        icon: '🔑',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'automotive',
        title: 'Service Loyalty Punch Card',
        description: 'QR at the service counter linked to a digital punch card. Every service visit earns a punch toward a free oil change.',
        implementation: 'Create a punch card promotion (e.g. 6 services = free oil change). Place QR at counter. Customers scan after each service.',
        icon: '🔧',
        difficulty: 'Easy',
        impact: 'Very High',
    },

    // ═══════════════════════════════════════
    // HOSPITALITY IDEAS
    // ═══════════════════════════════════════
    {
        category: 'hospitality',
        title: 'Room Key Sleeve QR',
        description: 'QR codes on key card sleeves. Guests scan for room service deals + hotel games.',
        implementation: 'Key sleeve QR. "Welcome! Scan for complimentary appetizer at our restaurant."',
        icon: '🔑',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'hospitality',
        title: 'Elevator Entertainment',
        description: 'QR codes in elevators. Guests play quick games for spa or dining discounts.',
        implementation: 'Elevator QR. "Going up? Scan to win spa or restaurant discounts!"',
        icon: '🛗',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'hospitality',
        title: 'Pillow Card QR',
        description: 'Evening turndown card with QR code. Scan for next-day breakfast deal.',
        implementation: 'Pillow card QR. "Sleep well! Scan for complimentary breakfast pastry tomorrow."',
        icon: '🛏️',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'hospitality',
        title: 'Pool Towel Tag QR',
        description: 'QR tags on pool towels. Guests scan for poolside service discounts.',
        implementation: 'Towel tag with QR. "Relaxing poolside? Scan for 20% off drinks!"',
        icon: '🏊',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'hospitality',
        title: 'Checkout Survey Game',
        description: 'QR at checkout desk. Play a quick game for a discount on next stay.',
        implementation: 'Checkout QR linked to a QRcade game. "Quick game before you go! Win up to 30% off your next stay."',
        icon: '📝',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'hospitality',
        title: 'Guest Loyalty Punch Card',
        description: 'QR at the front desk linked to a digital punch card. Repeat guests earn punches toward a free night or upgrade.',
        implementation: 'Create a punch card promotion. Guests scan at check-in. "5 stays = 1 free night! Scan to collect your punch."',
        icon: '🧳',
        difficulty: 'Easy',
        impact: 'Very High',
    },
    {
        category: 'hospitality',
        title: 'Mini Bar QR',
        description: 'QR on mini bar menu. Scan for "secret" discounted items not on regular menu.',
        implementation: 'Mini bar QR card. "Scan to unlock secret mini bar deals!"',
        icon: '🍾',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'hospitality',
        title: 'Partner Restaurant Cross-Promo',
        description: 'Partner with nearby restaurants using cross-promotions. Hotel guests scan for deals at partner restaurants, and diners get hotel discounts.',
        implementation: 'Set up cross-promotions with local restaurants. Concierge cards with QR. "Our top pick! Scan for 15% off dinner + they get a hotel deal."',
        icon: '🗺️',
        difficulty: 'Medium',
        impact: 'High',
    },

    // ═══════════════════════════════════════
    // HEALTHCARE IDEAS
    // ═══════════════════════════════════════
    {
        category: 'healthcare',
        title: 'Waiting Room Wellness',
        description: 'QR codes in waiting rooms linked to a QRcade game. Patients play while waiting and win small prizes.',
        implementation: 'Waiting room QR linked to a QRcade game. "Play while you wait! Win a discount on products or your next copay."',
        icon: '🏥',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'healthcare',
        title: 'Return Visit Discount Card',
        description: 'QR card given after appointments. Patients scan to claim a discount on their next visit or purchase.',
        implementation: 'QR card linked to a promotion. "Thanks for your visit! Scan for $10 off your next appointment."',
        icon: '📅',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'healthcare',
        title: 'Prescription Bag QR',
        description: 'QR codes on pharmacy bags. Customers scan for refill rewards.',
        implementation: 'Pharmacy bag QR linked to a promotion. "Get $5 off your next refill when you scan!"',
        icon: '💊',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'healthcare',
        title: 'New Patient Welcome QR',
        description: 'QR in new patient packets. Scan to claim a first-time patient welcome discount.',
        implementation: 'Create a First-Time Customer promotion. QR in welcome packet. "Welcome! Scan for $20 off your first visit."',
        icon: '👋',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'healthcare',
        title: 'Visit Loyalty Punch Card',
        description: 'QR in the lobby linked to a digital punch card. Patients scan each visit to build toward a free visit or product.',
        implementation: 'Create a punch card promotion. "Scan at each visit! 10 visits = free teeth whitening (or other reward)."',
        icon: '📈',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'healthcare',
        title: 'Dental Toothbrush QR',
        description: 'QR stickers on complimentary toothbrush packaging. Patients scan for a next-visit discount.',
        implementation: 'QR sticker on toothbrush package linked to a promotion. "Scan for 10% off your next cleaning!"',
        icon: '🦷',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'healthcare',
        title: 'Referral Thank-You Card',
        description: 'QR card to give patients who refer friends. When the referred friend scans, both get rewards.',
        implementation: 'Create a Merch Referral QR. "Thanks for the referral! When your friend scans this, you both get $25 off."',
        icon: '💌',
        difficulty: 'Easy',
        impact: 'Very High',
    },

    // ═══════════════════════════════════════
    // EDUCATION IDEAS
    // ═══════════════════════════════════════
    {
        category: 'education',
        title: 'Textbook QR Rewards',
        description: 'QR codes in textbooks linking to student discounts on tutoring or supplies.',
        implementation: 'QR sticker in chapter margins linked to a promotion. "Scan for 10% off tutoring sessions!"',
        icon: '📖',
        difficulty: 'Medium',
        impact: 'High',
    },
    {
        category: 'education',
        title: 'Library Study Punch Card',
        description: 'QR codes at library desks linked to a digital punch card. Students scan to check in and earn rewards after enough study sessions.',
        implementation: 'Create a punch card promotion. Library desk QR. "Check in! 10 study sessions = free coffee at the campus cafe."',
        icon: '📚',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'education',
        title: 'Course Completion Reward',
        description: 'QR code on course completion certificates. Students scan to claim a discount or reward as a congratulations.',
        implementation: 'QR on certificate linked to a promotion. "Congratulations! Scan to claim your $15 bookstore credit."',
        icon: '🏆',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'education',
        title: 'Tutoring Session Check-in',
        description: 'QR check-ins for tutoring sessions linked to a punch card. Track attendance + earn rewards.',
        implementation: 'Tutoring room QR linked to a punch card promotion. "Check in! 5 sessions = 1 FREE session."',
        icon: '👨‍🏫',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'education',
        title: 'Open House Info QR',
        description: 'QR codes at open house events. Prospective students scan for exclusive application perks.',
        implementation: 'Open house QR cards linked to a First-Time Customer promotion. "Attended our open house? Scan for application fee discount!"',
        icon: '🏫',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'education',
        title: 'Alumni Event QR',
        description: 'QR codes at alumni events. Scan to claim exclusive alumni deals and discounts.',
        implementation: 'Event badge QR linked to a promotion. "Welcome back! Scan for your alumni-exclusive discount."',
        icon: '🎉',
        difficulty: 'Easy',
        impact: 'Medium',
    },
    {
        category: 'education',
        title: 'Campus Scavenger Hunt',
        description: 'Place QRcade game QR codes across campus at different locations. Students play games at each spot for prizes.',
        implementation: 'Create multiple QRcade game QRs and place them around campus. Different games at each location with unique rewards. Promote it on social media!',
        icon: '🗺️',
        difficulty: 'Medium',
        impact: 'Very High',
    },

    // ═══════════════════════════════════════
    // EVENTS & VENUES IDEAS
    // ═══════════════════════════════════════
    {
        category: 'events',
        title: 'Event Ticket QR Upgrade',
        description: 'QR on tickets offering exclusive deals. Scan before or during the event for promotions.',
        implementation: 'Ticket QR linked to a promotion. "VIP perk! Scan for 20% off concessions or merch."',
        icon: '🎟️',
        difficulty: 'Easy',
        impact: 'High',
    },
    {
        category: 'events',
        title: 'Vendor Cross-Promo Trail',
        description: 'Event vendors partner using cross-promotions. Visitors scan at one vendor to unlock deals at partner vendors.',
        implementation: 'Set up cross-promotions between event vendors. "Scan here to unlock 15% off at the food truck next door!"',
        icon: '📷',
        difficulty: 'Medium',
        impact: 'High',
    },
    {
        category: 'events',
        title: 'Seat QR Entertainment',
        description: 'QR codes on stadium/arena seats. Scan during downtime to play a QRcade game and win concession deals.',
        implementation: 'Seat arm QR linked to QRcade game. "Halftime boredom? Play for a chance to win free nachos!"',
        icon: '🏟️',
        difficulty: 'Medium',
        impact: 'High',
    },
    {
        category: 'events',
        title: 'Level-Up Exclusive QR',
        description: 'Place level-exclusive QR codes at events. Only customers who\'ve reached a certain XP level can unlock the special reward.',
        implementation: 'Create a Level Exclusive QR code (e.g. Level 5+ required). "Loyal fans only! Scan to unlock your exclusive VIP reward."',
        icon: '💒',
        difficulty: 'Medium',
        impact: 'Very High',
    },
    {
        category: 'events',
        title: 'Festival Punch Card Trail',
        description: 'QR codes at different festival stages linked to a digital punch card. Visit all stages to complete the card and win a prize.',
        implementation: 'Create a punch card promotion with QR at each stage/area. "Visit all 5 stages! Scan at each one. Complete your card for a free t-shirt!"',
        icon: '🗺️',
        difficulty: 'Medium',
        impact: 'High',
    },
    {
        category: 'events',
        title: 'After-Party Access QR',
        description: 'Hidden QR codes at events. Find them for exclusive limited-time rewards.',
        implementation: 'Hidden QRs at venue linked to a promotion. "Found me! Scan for a limited-time reward."',
        icon: '🎊',
        difficulty: 'Easy',
        impact: 'Very High',
    },
    {
        category: 'events',
        title: 'First-Timer Event Welcome',
        description: 'QR code at event registration specifically for first-time attendees. Welcome them with a special deal.',
        implementation: 'Create a First-Time Customer promotion. QR at registration. "First time here? Scan for a welcome gift - 20% off merch!"',
        icon: '🎪',
        difficulty: 'Easy',
        impact: 'High',
    },
];

const filteredIdeas = computed(() => {
    if (selectedCategory.value === 'all') {
        return ideas;
    }
    return ideas.filter(idea => idea.category === selectedCategory.value);
});

const getDifficultyColor = (difficulty) => {
    const colors = {
        'Easy': 'bg-green-500/20 text-green-400',
        'Medium': 'bg-yellow-500/20 text-yellow-400',
        'Hard': 'bg-red-500/20 text-red-400',
    };
    return colors[difficulty] || 'bg-gray-500/20 text-gray-400';
};

const getImpactColor = (impact) => {
    const colors = {
        'Low': 'bg-gray-500/20 text-gray-400',
        'Medium': 'bg-blue-500/20 text-blue-400',
        'High': 'bg-purple-500/20 text-purple-400',
        'Very High': 'bg-pink-500/20 text-pink-400',
    };
    return colors[impact] || 'bg-gray-500/20 text-gray-400';
};
</script>

<template>
    <Head title="Promotion Ideas" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">💡 QR Code Promotion Ideas</h1>
                <p class="text-gray-400 mt-1">Creative ways to get the most out of QR codes for your business</p>
            </div>
            <Link href="/business/promotions" class="btn-glass">
                ← Back to Promotions
            </Link>
        </div>

        <!-- Intro Card -->
        <div class="glass-card p-6 mb-8 bg-gradient-to-r from-primary-500/10 to-accent-500/10">
            <div class="flex items-start gap-4">
                <div class="text-4xl">🚀</div>
                <div>
                    <h2 class="text-xl font-semibold text-white mb-2">Maximize Your QR Code Impact</h2>
                    <p class="text-gray-300">
                        QR codes are everywhere - but the magic is in <span class="text-primary-400 font-semibold">where</span> you place them and <span class="text-accent-400 font-semibold">what</span> happens when customers scan. 
                        Browse these {{ ideas.length }}+ proven ideas to transform every customer touchpoint into an engagement opportunity.
                        Every idea below can be built right now using your promotions, QRcade games, punch cards, cross-promotions, and more.
                    </p>
                </div>
            </div>
        </div>

        <!-- Category Filter -->
        <div class="mb-8">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    @click="selectedCategory = cat.id"
                    :class="[
                        'px-4 py-2 rounded-lg text-sm font-medium transition-all',
                        selectedCategory === cat.id
                            ? 'bg-primary-500 text-white'
                            : 'bg-white/10 text-gray-300 hover:bg-white/20'
                    ]"
                >
                    <span class="mr-2">{{ cat.icon }}</span>
                    {{ cat.name }}
                </button>
            </div>
        </div>

        <!-- Results Count -->
        <div class="mb-6 text-gray-400">
            Showing {{ filteredIdeas.length }} idea{{ filteredIdeas.length !== 1 ? 's' : '' }}
            <span v-if="selectedCategory !== 'all'">
                in {{ categories.find(c => c.id === selectedCategory)?.name }}
            </span>
        </div>

        <!-- Ideas Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
                v-for="(idea, index) in filteredIdeas"
                :key="index"
                class="glass-card p-6 hover:bg-white/5 transition-all group"
            >
                <!-- Header -->
                <div class="flex items-start justify-between mb-4">
                    <span class="text-3xl">{{ idea.icon }}</span>
                    <div class="flex gap-2">
                        <span :class="['px-2 py-1 rounded text-xs font-medium', getDifficultyColor(idea.difficulty)]">
                            {{ idea.difficulty }}
                        </span>
                        <span :class="['px-2 py-1 rounded text-xs font-medium', getImpactColor(idea.impact)]">
                            {{ idea.impact }} Impact
                        </span>
                    </div>
                </div>

                <!-- Title -->
                <h3 class="text-lg font-semibold text-white group-hover:text-primary-400 transition-colors mb-2">
                    {{ idea.title }}
                </h3>

                <!-- Description -->
                <p class="text-gray-400 text-sm mb-4">
                    {{ idea.description }}
                </p>

                <!-- Implementation -->
                <div class="bg-white/5 rounded-lg p-3 mb-4">
                    <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">How to implement</div>
                    <p class="text-gray-300 text-sm">{{ idea.implementation }}</p>
                </div>

                <!-- Category Tag -->
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">
                        {{ categories.find(c => c.id === idea.category)?.icon }}
                        {{ categories.find(c => c.id === idea.category)?.name }}
                    </span>
                    <Link 
                        href="/business/promotions/create" 
                        class="text-primary-400 hover:text-primary-300 text-sm font-medium transition-colors"
                    >
                        Create This →
                    </Link>
                </div>
            </div>
        </div>

        <!-- Bottom CTA -->
        <div class="mt-12 glass-card p-8 text-center bg-gradient-to-r from-primary-500/10 to-accent-500/10">
            <h2 class="text-2xl font-bold text-white mb-4">Ready to Get Started?</h2>
            <p class="text-gray-300 mb-6 max-w-2xl mx-auto">
                Pick any idea above and bring it to life in minutes. Create your promotion, generate QR codes, and start engaging customers today.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <Link href="/business/promotions/create" class="btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Create a Promotion
                </Link>
                <Link href="/business/qr-codes/create" class="btn-glass">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                    Create a QR Code
                </Link>
                <Link href="/business/qrcade" class="btn-glass">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Set Up Games
                </Link>
            </div>
        </div>

        <!-- Pro Tips Section -->
        <div class="mt-12">
            <h2 class="text-xl font-bold text-white mb-6">💎 Pro Tips for QR Success</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="glass-card p-4">
                    <div class="text-2xl mb-2">📍</div>
                    <h3 class="font-semibold text-white mb-1">Location Matters</h3>
                    <p class="text-gray-400 text-sm">Place QR codes where customers naturally pause - waiting areas, checkout lines, tables.</p>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl mb-2">🎯</div>
                    <h3 class="font-semibold text-white mb-1">Clear Call-to-Action</h3>
                    <p class="text-gray-400 text-sm">Always tell customers what they'll get: "Scan to win!" beats "Scan me" every time.</p>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl mb-2">🔄</div>
                    <h3 class="font-semibold text-white mb-1">Dynamic QR Codes</h3>
                    <p class="text-gray-400 text-sm">Use dynamic QR codes so you can change the destination without reprinting.</p>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl mb-2">📊</div>
                    <h3 class="font-semibold text-white mb-1">Track Everything</h3>
                    <p class="text-gray-400 text-sm">Use analytics to see which placements drive the most scans and conversions.</p>
                </div>
            </div>
        </div>

        <!-- Industry Stats -->
        <div class="mt-12 glass-card p-6">
            <h2 class="text-xl font-bold text-white mb-6 text-center">📈 Why QR Codes Work</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <div>
                    <div class="text-3xl font-bold gradient-text mb-1">94%</div>
                    <div class="text-gray-400 text-sm">of smartphone users have scanned a QR code</div>
                </div>
                <div>
                    <div class="text-3xl font-bold gradient-text mb-1">59%</div>
                    <div class="text-gray-400 text-sm">say QR codes will be permanent part of their phone use</div>
                </div>
                <div>
                    <div class="text-3xl font-bold gradient-text mb-1">45%</div>
                    <div class="text-gray-400 text-sm">higher engagement than traditional ads</div>
                </div>
                <div>
                    <div class="text-3xl font-bold gradient-text mb-1">3x</div>
                    <div class="text-gray-400 text-sm">more likely to return with gamified rewards</div>
                </div>
            </div>
        </div>
    </div>
</template>
