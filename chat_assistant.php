<?php
declare(strict_types=1);

/** Site-grounded answers for Davao Local support. Never read private user data here. */
function davao_help_answer(PDO $pdo, string $message, string $previousUserMessage = ''): string
{
    $text = trim(mb_strtolower($message, 'UTF-8'));
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    if ($text === '') return 'Please enter a question. I can help with directory search, contacting a business, customer accounts, owner listings, appointments, and the map.';
    $isFollowUp = preg_match('/^(where|how much|price|prices|hours|address|phone|location|and (?:hours|prices?|location|address|phone)|what about (?:(?:its|the) )?(?:hours|prices?|location|address|phone)|is it verified|tell me more|and there)[?.! ]*$/u', $text) === 1;
    $previousMentionsBusiness = preg_match('/\b(about|called|named|phone(?: number)? for|hours for|address for|contact details for|where is|where can i find)\b/u', mb_strtolower($previousUserMessage, 'UTF-8')) === 1;
    $contextBusinessFollowUp = $isFollowUp && $previousMentionsBusiness;
    $namedStatusQuestion = preg_match('/^(?:is|are|does) (?!it\b|there\b|the directory\b|the map\b).{3,}$/u', $text) && preg_match('/\b(verified|open|closed|hours|phone|address|location|price|cost)\b/u', $text);

    // Resolve direct, more specific intents before looking for business names.
    if (preg_match('/\b(how (?:can|do) i|where (?:can|do) i|can i|i want to|need to)\b.*\b(contact|get in touch|reach|message|talk to|speak to|ask)\b|\b(contact|get in touch|message|reach)\s+(?:the\s+)?(?:business|owner)\b/u', $text)) {
        return 'To contact a business: **1.** Open [Directory](index.php?page=directory) and choose a listing. **2.** Sign in or register as a Customer. **3.** On its profile, use **Send inquiry**. The conversation is private to your account and the business owner; open [Messages](messages.php) to continue it. Public phone or email details are shown on the profile when the listing has them.';
    }
    if (preg_match('/\b(register|registration|sign ?up|create (?:an? )?account|join)\b/u', $text)) {
        if (preg_match('/\b(business|owner|listing)\b/u', $text)) return 'To submit a business listing, [register](index.php?page=register) and choose **Business owner**, then sign in and open [My Business](owner.php). Use **Add Another Business** to submit it for admin approval. Approval and verification are separate.';
        return 'Choose [Join us](index.php?page=register), enter your name and email, set a password, and choose **Customer** or **Business owner**. After registering, use [Log in](index.php?page=login).';
    }
    if (preg_match('/\b(log ?in|sign ?in|password|forgot password)\b/u', $text)) {
        return 'Open [Log in](index.php?page=login) and use your account email and password. If you have not registered, choose [Join us](index.php?page=register). This app does not provide a password-recovery flow.';
    }
    if (preg_match('/\b(my messages|my appointments|my account|my profile|my password|show me my|read my|my private|someone else.s messages|another user.s)\b/u', $text)) {
        return 'I can’t read or disclose personal account data, messages, or appointments in chat. Sign in to your account and open [Messages](messages.php) or [Appointments](appointments.php) to view your own information. Account details can be managed from your account workspace.';
    }
    if (preg_match('/\b(my business|my listing|manage my listing|owner dashboard|owner account|add another business|business owner|list my business)\b/u', $text)) {
        return 'Sign in with a Business owner account, then open [My Business](owner.php) to view and manage your own listings. Use **Add Another Business** to submit a listing for admin approval.';
    }
    if (!$contextBusinessFollowUp && !$namedStatusQuestion && preg_match('/\b(approval|approved|pending|reject(?:ed)?|verification|verified|suspend(?:ed)?)\b/u', $text)) {
        return 'A submitted listing waits for admin approval. Approved listings can appear in the public directory; verification is a separate status. Owners can check their listing status in [My Business](owner.php).';
    }
    if (preg_match('/\b(save|saved|favorite|favourite|bookmark)\b/u', $text)) {
        return 'Sign in or register as a Customer, open a business profile, and select **Save**. Your saved listings are in [Saved](customer.php#saved).';
    }
    if (preg_match('/\b(appointment|booking|book|schedule|reschedule|cancel)\b/u', $text)) {
        return 'To request an appointment, sign in as a Customer, open the business profile, choose a service and time, then submit the request. Review its status in [Appointments](appointments.php). The owner handles the request; chat cannot change it.';
    }
    if (preg_match('/\b(review|rating|stars)\b/u', $text)) {
        return 'Sign in as a Customer, open the business profile, and submit a star rating and comment in its Reviews section. Reviews are shown publicly after moderation.';
    }
    if (!$contextBusinessFollowUp && preg_match('/\b(map|location|directions|nearby|where.*business|area|barangay)\b/u', $text) && !preg_match('/\b(which|what|tell me about)\b.*\b(business|shop|store|place)\b/u', $text)) {
        return 'Use [Directory](index.php?page=directory) to search by area or barangay. The [Map](index.php?page=map) shows only approved non-sample listings with usable saved coordinates. A profile shows its saved address and directions when available.';
    }
    if (preg_match('/\b(search|find|browse|directory|businesses)\b/u', $text) && !preg_match('/\b(?:about|called|named|phone for|hours for|address for)\b/u', $text)) {
        return 'Open [Directory](index.php?page=directory). Search approved listings by name or keyword, then narrow by category or area and open a result for its saved contact, services, hours, and location details.';
    }
    if ($isFollowUp && !$contextBusinessFollowUp) return 'Which business or feature do you mean? Give me the business name, or tell me whether you mean your account, messages, appointments, Saved, or owner listings.';

    $lookupText = $text;
    $followUp = $isFollowUp;
    if ($followUp && trim($previousUserMessage) !== '') {
        $prior = mb_strtolower(trim($previousUserMessage), 'UTF-8');
        if (preg_match('/\b(about|called|named|phone for|hours for|address for|where is|where can i find)\b/u', $prior)) {
            $lookupText = $prior;
        } else {
            return 'What business or feature are you asking about? Tell me its name, or ask about Directory, Messages, appointments, Saved, owner listings, or Maps.';
        }
    }

    $query = $lookupText;
    foreach ([
        '/^.*?\b(?:tell me about|details for|information about|info for|hours for|address for|phone(?: number)? for|contact details for|where is|where can i find)\s+/u',
        '/^.*?\b(?:called|named)\s+/u',
        '/^(?:what is|what are|do you have|is there|show me)\s+/u',
        '/^(?:is|are|does)\s+/u',
    ] as $prefix) $query = preg_replace($prefix, '', $query, 1) ?? $query;
    $query = preg_replace('/\b(?:the|its|their|business|businesses|listing|please|thanks|any|number|services|where|contact|phone|email|address|location|barangay|district|hours|opening|closing|open|close|now|today|currently|verified|verification|price|prices|cost|costs|rate|rates|in davao(?: city)?|in the directory)\b/u', ' ', $query) ?? $query;
    $query = trim(preg_replace('/[^\p{L}\p{N} &\'-]+/u', ' ', $query) ?? $query);

    $lookupIntent = $followUp || $namedStatusQuestion || preg_match('/\b(about|called|named|phone(?: number)? for|hours for|address for|contact details for|where is|where can i find|do you have|is there|what is|what are)\b/u', $text) || preg_match('/\b(?:business|shop|store|clinic|restaurant|printing)\b/u', $text);
    if ($lookupIntent && mb_strlen($query) >= 2) {
        $businesses = find_public_davao_businesses($pdo, $query);
        if (count($businesses) === 1) return format_public_business_answer($pdo, $businesses[0], $text, $followUp);
        if (count($businesses) > 1) {
            $lines = ['I found several approved, non-sample listings. Which one do you mean?'];
            foreach ($businesses as $business) $lines[] = '- [' . $business['name'] . '](index.php?page=business&id=' . (int)$business['id'] . ') — ' . $business['category'] . ', ' . ($business['barangay'] ?: $business['city']);
            return implode("\n", $lines);
        }
        return 'I could not find an approved, non-sample Davao Local listing matching **' . $query . '**. Try another spelling or search the [Directory](index.php?page=directory). I can only answer from current public listings.';
    }

    if (preg_match('/\b(product|service|gallery|photo|image)\b/u', $text)) {
        return 'Public profiles show services, available products, and gallery images when the business has added them. Owners manage these from their [My Business](owner.php) workspace. I can check a specific public listing if you tell me its name.';
    }
    if (preg_match('/\b(notification|alert)\b/u', $text)) return 'Sign in and open [Notifications](notifications.php) to see updates for your account.';
    if (preg_match('/\b(report|moderation|admin|category)\b/u', $text)) return 'Customers can report a listing from its business profile. Administrators review reports and manage listings in [Admin](admin.php); the admin workspace requires an admin account.';

    return 'I’m not sure what you mean yet. I can help with finding a listed business, contacting a business, customer accounts, saved listings, appointments, reviews, the map, or owner listing management. What would you like to do?';
}

/** Search only records that are publicly visible and explicitly not sample data. */
function find_public_davao_businesses(PDO $pdo, string $name): array
{
    $needle = '%' . addcslashes($name, '%_\\') . '%';
    if (trim($name) === '') return [];
    $stmt = $pdo->prepare("SELECT b.id,b.name,b.category_id,b.city,b.barangay,b.district,b.address,b.contact_phone,b.contact_email,b.description,b.is_verified,b.business_status,c.name AS category FROM businesses b JOIN categories c ON c.id=b.category_id WHERE b.status='approved' AND b.is_demo=0 AND b.city='Davao City' AND (b.name LIKE ? OR c.name LIKE ?) ORDER BY CASE WHEN LOWER(b.name)=LOWER(?) THEN 0 ELSE 1 END,b.name LIMIT 8");
    $stmt->execute([$needle, $needle, $name]);
    return $stmt->fetchAll();
}

function format_public_business_answer(PDO $pdo, array $business, string $question, bool $followUp): string
{
    $id = (int) $business['id'];
    $lines = ['**' . $business['name'] . '** — ' . $business['category'] . ' (' . ($business['city'] ?: 'Davao City') . ').'];
    $location = trim(implode(', ', array_filter([$business['address'], $business['barangay'], $business['district'], $business['city']])));
    $hoursQuery = $pdo->prepare('SELECT day_of_week,opens,closes,is_closed FROM business_hours WHERE business_id=? ORDER BY day_of_week');
    $hoursQuery->execute([$id]); $hours = $hoursQuery->fetchAll();
    $serviceQuery = $pdo->prepare('SELECT s.name,bs.price FROM business_services bs JOIN services s ON s.id=bs.service_id WHERE bs.business_id=? ORDER BY s.name');
    $serviceQuery->execute([$id]); $services = $serviceQuery->fetchAll();
    $productQuery = $pdo->prepare("SELECT name,price FROM products WHERE business_id=? AND availability='available' ORDER BY name");
    $productQuery->execute([$id]); $products = $productQuery->fetchAll();
    $isHours = preg_match('/\b(hours|open|opening|close|closing)\b/u', $question) === 1;
    $isPrice = preg_match('/\b(price|cost|how much|rate)\b/u', $question) === 1;
    $isLocation = preg_match('/\b(where|location|address|barangay|district)\b/u', $question) === 1;
    if (preg_match('/\bverified\b/u', $question)) $lines[] = 'Verification: ' . ((int)$business['is_verified'] === 1 ? 'the listing is marked verified.' : 'the listing is not marked verified.');
    if (preg_match('/\b(open now|open today|currently open|closed now)\b/u', $question)) {
        if ($business['business_status'] === 'temporarily_closed') $lines[] = 'Current status: temporarily closed (owner-set status).';
        elseif ($business['business_status'] === 'closed') $lines[] = 'Current status: closed (owner-set status).';
        else {
            $today = (int)date('w'); $todayHours = null;
            foreach ($hours as $hour) if ((int)$hour['day_of_week'] === $today) { $todayHours = $hour; break; }
            if (!$todayHours) $lines[] = 'Current status: hours for today are not provided.';
            elseif ((int)$todayHours['is_closed']) $lines[] = 'Current status: closed today according to saved hours.';
            else { $now = date('H:i:s'); $open = $todayHours['opens'] <= $now && $now < $todayHours['closes']; $lines[] = 'Current status: ' . ($open ? 'open now' : 'closed now') . ' according to saved hours and owner-set status.'; }
        }
    }
    if (!$followUp || $isLocation) $lines[] = $location !== '' ? 'Address: ' . $location . '.' : 'Address: not provided in the public listing.';
    if (!$followUp || preg_match('/\b(phone|contact)\b/u', $question)) $lines[] = !empty($business['contact_phone']) ? 'Phone: ' . $business['contact_phone'] . '.' : 'Phone: not provided in the public listing.';
    if (!$followUp || preg_match('/\b(email|contact)\b/u', $question)) $lines[] = !empty($business['contact_email']) ? 'Email: ' . $business['contact_email'] . '.' : 'Email: not provided in the public listing.';
    if ($isHours || !$followUp) {
        $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        if (!$hours) $lines[] = 'Hours: not provided in the public listing.';
        else {
            $formatted = [];
            foreach ($hours as $hour) $formatted[] = $days[(int)$hour['day_of_week']] . ': ' . ((int)$hour['is_closed'] ? 'Closed' : substr((string)$hour['opens'], 0, 5) . '–' . substr((string)$hour['closes'], 0, 5));
            $lines[] = 'Saved hours: ' . implode('; ', $formatted) . '.';
        }
    }
    if ($isPrice || !$followUp) {
        $priced = array_values(array_filter($services, static fn(array $s): bool => $s['price'] !== null));
        $productPrices = array_values(array_filter($products, static fn(array $p): bool => $p['price'] !== null));
        $priceLines = array_merge(
            array_map(static fn(array $s): string => $s['name'] . ' ₱' . number_format((float)$s['price'], 2), $priced),
            array_map(static fn(array $p): string => $p['name'] . ' ₱' . number_format((float)$p['price'], 2), $productPrices)
        );
        $lines[] = $priceLines ? 'Listed prices: ' . implode('; ', $priceLines) . '.' : 'No service or product prices are provided in the public listing.';
    }
    if (!$followUp && !$isHours && !$isPrice) {
        $lines[] = $services ? 'Services: ' . implode(', ', array_column($services, 'name')) . '.' : 'No services are listed publicly.';
        if (!empty($business['description'])) $lines[] = 'About: ' . $business['description'];
    }
    $lines[] = 'Profile: [view listing](index.php?page=business&id=' . $id . ').';
    return implode("\n", $lines);
}
