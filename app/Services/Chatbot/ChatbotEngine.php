<?php

namespace App\Services\Chatbot;

/**
 * ENSALADA chatbot brain — rule-based, zero cost, no external calls.
 *
 * Pure PHP: it takes a plain-array "context" (branch, menu, settings, FAQs)
 * and a message, and returns a reply array. It knows nothing about Eloquent,
 * HTTP or caching, which keeps it trivial to test. See ChatContextBuilder for
 * how the context is assembled from the database.
 *
 * Context shape:
 *   branch     ?array   currently selected branch (null = none chosen yet)
 *   branches   array[]  all active branches
 *   categories array[]  categories offered at the branch
 *   items      array[]  menu items available at the branch (price flattened)
 *   settings   array    site_settings key => value for the branch
 *   faqs       array[]  admin-managed answers: keywords[], answer{en,es}, branch_id
 *
 * Reply shape:
 *   text, items[], actions[], chips[], is_fallback
 */
final class ChatbotEngine
{
    private const DEFAULTS = [
        'address' => '123 Green Leaf Avenue, Beirut, Lebanon',
        'phone' => '+961 1 123 456',
        'email' => 'hello@ensalada.com',
        'hours_weekday' => 'Mon – Fri: 10am – 9pm',
        'hours_weekend' => 'Sat – Sun: 11am – 10pm',
        'about' => 'ENSALADA is a modern salad restaurant serving fresh, colorful bowls made daily.',
    ];

    private const MAX_CARDS = 4;

    private const GENERIC = [
        'salad', 'bowl', 'with', 'and', 'the', 'of', 'a', 'an', 'in', 'on',
        'fresh', 'plate', 'our', 'your', 'to', 'for', 'is', 'are', 'do', 'you', 'have',
    ];

    private const TAG_SYNONYMS = [
        'vegan' => ['plant based', 'plantbased'],
        'vegetarian' => ['veggie', 'veggies', 'meatless', 'meat free', 'no meat', 'without meat'],
        'gluten free' => ['gluten', 'celiac', 'coeliac'],
        'dairy free' => ['lactose', 'no dairy'],
        'high protein' => ['protein rich'],
        'spicy' => ['spice'],
        'keto' => ['low carb'],
    ];

    /** Dietary words understood even when no dish at the branch has the tag. */
    private const DIET_TERMS = ['vegan', 'vegetarian', 'gluten free', 'dairy free', 'keto'];

    private const SORT_LABEL = [
        'cal-asc' => 'lightest',
        'protein-desc' => 'highest-protein',
        'price-asc' => 'most affordable',
        'price-desc' => 'premium',
    ];

    /* ───────────────────────── public API ───────────────────────── */

    public function reply(string $input, array $ctx, int $fallbackStreak = 0): array
    {
        $ctx = $this->prepare($ctx);

        return $this->finalize($this->route($input, $ctx, $fallbackStreak), $ctx);
    }

    /**
     * @param  'welcome'|'switched'|'chosen'  $mode
     */
    public function welcome(array $ctx, string $mode = 'welcome'): array
    {
        $ctx = $this->prepare($ctx);

        if (empty($ctx['branch'])) {
            return $this->finalize(
                $this->r(
                    'Hi, welcome to ENSALADA! Which branch are you interested in? Menu, prices and hours vary by location.',
                    ['actions' => $this->branchActions($ctx)],
                ),
                $ctx,
            );
        }

        $name = $this->branchName($ctx['branch']);
        $text = match ($mode) {
            'switched' => "You're now viewing {$name}. Menu, prices and hours are updated — what would you like to know?",
            'chosen' => "Great, {$name} it is! What would you like to know?",
            default => "Hi, welcome to ENSALADA {$name}! I can help with the menu, prices, nutrition, opening hours and how to find us. What are you in the mood for?",
        };

        return $this->finalize($this->r($text, ['starters' => true]), $ctx);
    }

    public function starterChips(array $ctx): array
    {
        $ctx = $this->prepare($ctx);
        $chips = ["What's on the menu?", 'What do you recommend?'];

        $tags = array_map('mb_strtolower', $this->uniqueTags($ctx['items']));
        foreach ($tags as $tag) {
            if (str_contains($tag, 'vegan')) {
                $chips[] = 'Vegan options';
                break;
            }
        }
        foreach ($ctx['items'] as $i) {
            if (($i['protein_grams'] ?? null) !== null) {
                $chips[] = 'High-protein picks';
                break;
            }
        }
        foreach ($ctx['items'] as $i) {
            if (($i['calories'] ?? null) !== null) {
                $chips[] = 'Low-calorie options';
                break;
            }
        }
        array_push($chips, 'Opening hours', 'Where are you?');

        return array_slice($chips, 0, 6);
    }

    /* ───────────────────────── routing ───────────────────────── */

    private function route(string $input, array $ctx, int $fallbackStreak): array
    {
        $t = $this->normalize($input);
        $msg = array_flip($this->words($input));
        $wordCount = count(array_filter(explode(' ', $t), 'strlen'));
        $noBranch = empty($ctx['branch']);

        if ($t === '') {
            return $this->r('Go ahead — ask me anything about ENSALADA.', ['starters' => true]);
        }

        // ── small talk ──
        $greetRe = '~^(?:hi+|hello+|hey+|hola|howdy|yo|sup|marhaba|salam|good (?:morning|afternoon|evening))\b~';
        $afterGreeting = trim((string) preg_replace($greetRe, '', $t, 1));
        if (preg_match($greetRe, $t) && preg_match('~^(?:there|everyone|guys|team|ensalada|friend|bro|all)?$~', $afterGreeting)) {
            return $this->r('Hello! How can I help you today?', ['starters' => true]);
        }
        if (preg_match('~\bhow are you\b|\bhows it going\b~', $t) && $wordCount <= 6) {
            return $this->r('Doing great, thanks for asking! Hungry for something fresh?', ['starters' => true]);
        }
        if (preg_match('~\b(?:thanks?|thank you|thx|merci|shukran|gracias|appreciate)\b~', $t) && $wordCount <= 4) {
            return $this->r("You're very welcome! Anything else I can help with?", ['chips' => ["What's on the menu?", 'Opening hours']]);
        }
        if (preg_match('~\b(?:bye|goodbye|see you|cya|ttyl)\b~', $t) && $wordCount <= 5) {
            return $this->r('Goodbye! Hope to see you at ENSALADA soon.');
        }
        if (preg_match('~^(?:ok|okay|cool|great|nice|perfect|awesome|got it|alright|sure)\b~', $t) && $wordCount <= 3) {
            return $this->r("Glad to help! Anything else you'd like to know?", ['starters' => true]);
        }
        if (preg_match('~^(?:help|help me|what can you do|what do you know|how does this work)$~', $t)) {
            return $this->r(
                "I can help you with:\n• The menu, categories and prices\n• Calories and protein for each dish\n• Vegan, vegetarian and other dietary picks\n• Opening hours, address and phone\n• Our branches and social links\n\nJust type a question or tap a suggestion.",
                ['starters' => true],
            );
        }

        // ── admin-managed FAQ answers (win over the built-in rules) ──
        $faq = $this->matchFaq($input, $ctx);
        if ($faq !== null) {
            return $this->r($faq, ['chips' => ["What's on the menu?", 'Opening hours']]);
        }

        // ── a specific dish ──
        [$full, $partial] = $this->matchItems($t, $msg, $ctx, $wordCount);
        if (count($full) === 1) {
            return $this->itemDetail($full[0], $t, $ctx);
        }
        if (count($full) > 1) {
            return $this->r('I found '.count($full).' dishes that match:', [
                'items' => $this->cards($full),
                'chips' => ['Show full menu'],
            ]);
        }

        // ── branches ──
        if (preg_match('~\bbranch(?:es)?\b|\boutlets?\b|\ball (?:your )?locations\b|\bhow many (?:locations|places|stores)\b~', $t)) {
            return $this->branchesList($ctx);
        }

        // ── hours ──
        if (preg_match('~\b(?:hours?|open(?:s|ing)?|clos(?:e|es|ing)|schedule|timings?|what time|until when|when are you)\b|\bhorario\b~', $t)) {
            if ($noBranch) {
                return $this->askBranch($ctx);
            }
            $wk = $this->setting($ctx, 'hours_weekday', self::DEFAULTS['hours_weekday']);
            $we = $this->setting($ctx, 'hours_weekend', self::DEFAULTS['hours_weekend']);

            return $this->r("Our opening hours{$this->at($ctx)}:\n{$wk}\n{$we}", ['chips' => ['Where are you?', 'Phone number']]);
        }

        // ── location ──
        if (preg_match('~\b(?:address|located|location|where (?:are|r) (?:you|u)|where is|where can i find you|find you|directions?|how (?:do i|to) get|map|near me|nearby|come visit)\b~', $t)) {
            if ($noBranch) {
                return $this->branchesList($ctx);
            }
            $addr = $this->setting($ctx, 'contact_address', ($ctx['branch']['address'] ?? null) ?: self::DEFAULTS['address']);
            $more = count($ctx['branches']) > 1
                ? ' We have '.count($ctx['branches']).' branches — ask for “all branches” to see them.'
                : '';

            return $this->r("Our {$this->branchName($ctx['branch'])} branch is at {$addr}.{$more}", [
                'actions' => [
                    ['kind' => 'href', 'label' => 'Open in Maps', 'href' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($addr)],
                    ['kind' => 'link', 'label' => 'Contact page', 'to' => '/contact'],
                ],
                'chips' => ['Opening hours', 'Phone number'],
            ]);
        }

        // ── phone ──
        if (preg_match('~\b(?:phone|telephone|whatsapp|hotline|call (?:you|us)|contact number|ring)\b~', $t)) {
            if ($noBranch) {
                return $this->askBranch($ctx);
            }
            $phone = $this->phone($ctx);

            return $this->r("You can call us{$this->at($ctx)} on {$phone}.", [
                'actions' => [$this->callAction($phone)],
                'chips' => ['Opening hours', 'Where are you?'],
            ]);
        }

        // ── email ──
        if (preg_match('~\be-?mail\b~', $t)) {
            $email = $this->setting($ctx, 'contact_email', self::DEFAULTS['email']);

            return $this->r("Our email is {$email}. You can also use the contact form and we'll get back to you.", [
                'actions' => [
                    ['kind' => 'href', 'label' => "Email {$email}", 'href' => "mailto:{$email}"],
                    ['kind' => 'link', 'label' => 'Contact form', 'to' => '/contact'],
                ],
            ]);
        }

        // ── social ──
        if (preg_match('~\b(?:instagram|facebook|tiktok|twitter|youtube|snapchat|linkedin|social(?: media)?|follow (?:you|us))\b~', $t)) {
            $links = array_slice($this->socialLinks($ctx), 0, 4);
            if (! $links) {
                return $this->r("You'll find our social links in the footer and on the contact page.", [
                    'actions' => [['kind' => 'link', 'label' => 'Contact page', 'to' => '/contact']],
                ]);
            }

            return $this->r("Come say hi! Here's where to find us online:", [
                'actions' => array_map(fn ($l) => ['kind' => 'href', 'label' => $l['platform'], 'href' => $l['url']], $links),
            ]);
        }

        // ── orders / reservations ──
        if (preg_match('~\b(?:deliver(?:y|ies)?|takeaway|take away|take out|takeout|pick ?up|order(?:ing)?|reserve|reservations?|book(?:ing)? a table|table for)\b~', $t)) {
            if ($noBranch) {
                return $this->askBranch($ctx);
            }
            $phone = $this->phone($ctx);

            return $this->r(
                "I can't place orders or take reservations in this chat, and I don't have delivery details. The team can help directly — call {$phone}{$this->at($ctx)} or send us a message.",
                ['actions' => [$this->callAction($phone), ['kind' => 'link', 'label' => 'Contact form', 'to' => '/contact']]],
            );
        }

        // ── things only a human knows ──
        if (preg_match('~\b(?:contact|reach|get in touch|speak to|talk to|human|complain|complaint|feedback|catering|parking|wifi|jobs?|careers?|hiring|birthday|payment|pay by|credit card|cash|accessible|wheelchair|kids?)\b~', $t)) {
            $phone = $this->phone($ctx);
            $email = $this->setting($ctx, 'contact_email', self::DEFAULTS['email']);

            return $this->r(
                "For that, the team is the best source. Reach us on {$phone} or {$email}, or use the contact form.",
                ['actions' => [['kind' => 'link', 'label' => 'Contact form', 'to' => '/contact'], $this->callAction($phone)]],
            );
        }

        // ── about ──
        if (preg_match('~\b(?:about (?:you|us|ensalada|the restaurant)|your story|who are you|what is ensalada|tell me about)\b~', $t)) {
            return $this->r($this->setting($ctx, 'about_body_1', self::DEFAULTS['about']), [
                'actions' => [['kind' => 'link', 'label' => 'Read more', 'to' => '/home']],
                'chips' => ["What's on the menu?"],
            ]);
        }

        // ── menu filters: tags, category, calories, protein, price, sorting ──
        $q = $this->parseQuery($t, $msg, $ctx);
        if ($this->hasQuery($q)) {
            if ($noBranch) {
                return $this->askBranch($ctx);
            }

            return $this->queryReply($q, $ctx, $t);
        }

        // ── ingredient search: “do you have chicken?”, “with avocado” ──
        if (preg_match('~\b(?:do you have|have you got|any|with|contains?|containing|includes?|made with|has)\s+(.+)$~', $t, $ing)) {
            $terms = array_values(array_filter(
                $this->words($ing[1] ?? ''),
                fn ($w) => ! in_array($w, self::GENERIC, true) && strlen($w) > 2,
            ));
            if ($terms) {
                $found = array_values(array_filter($ctx['items'], function ($it) use ($terms) {
                    $hay = array_flip($this->words($this->loc($it['name'] ?? null).' '.$this->loc($it['description'] ?? null)));
                    foreach ($terms as $w) {
                        if (! isset($hay[$w])) {
                            return false;
                        }
                    }

                    return true;
                }));
                if ($found) {
                    $n = count($found);

                    return $this->r("Yes! {$n} dish".($n === 1 ? '' : 'es').' with '.implode(' ', $terms).$this->at($ctx).':', [
                        'items' => $this->cards($found),
                        'chips' => ['Show full menu'],
                    ]);
                }
                if (! $noBranch && $ctx['items']) {
                    return $this->r(
                        "I couldn't find ".implode(' ', $terms)." on the menu{$this->at($ctx)}. Want to see what we do serve?",
                        ['chips' => ['Show full menu', 'What do you recommend?']],
                    );
                }
            }
        }

        // ── partial dish match ──
        if ($partial) {
            return $this->r("These dishes might be what you're after:", [
                'items' => $this->cards($partial),
                'chips' => ['Show full menu'],
            ]);
        }

        // ── nutrition without a dish ──
        if (preg_match('~\bcal|\bkcal|nutrition|macro~', $t)) {
            return $this->r("Tell me a dish name and I'll share its calories and protein — or ask for low-calorie or high-protein picks.", [
                'chips' => ['Low-calorie options', 'High-protein picks'],
            ]);
        }

        // ── menu overview ──
        if (
            preg_match('~\b(?:menu|categories|category|what do you (?:have|serve|sell|offer)|what (?:food|can i (?:eat|get))|dishes|food|offer|serve)\b~', $t)
            || $t === 'show full menu'
            || ($wordCount <= 2 && preg_match('~\b(?:salads?|bowls?)\b~', $t))
        ) {
            return $noBranch ? $this->askBranch($ctx) : $this->menuOverview($ctx);
        }

        // ── menu-ish question but no branch chosen yet ──
        if ($noBranch && preg_match('~\b(?:menu|vegan|vegetarian|salads?|bowls?|calor|protein|price|prices|cheap|recommend|food|dish|dishes|eat|gluten|healthy|light|popular|sides?)\b~', $t)) {
            return $this->askBranch($ctx);
        }

        // ── fallback ──
        if ($fallbackStreak >= 1) {
            $phone = $this->phone($ctx);

            return $this->r(
                "I'm still not sure about that one. For anything I can't answer, the team will be happy to help — call {$phone} or use the contact form.",
                [
                    'is_fallback' => true,
                    'actions' => [['kind' => 'link', 'label' => 'Contact form', 'to' => '/contact']],
                    'chips' => ["What's on the menu?", 'Opening hours'],
                ],
            );
        }

        return $this->r(
            "Sorry, I didn't quite get that. I can help with the menu, prices, nutrition, opening hours and our location — try one of these:",
            ['is_fallback' => true, 'starters' => true],
        );
    }

    /* ───────────────────────── FAQ ───────────────────────── */

    /**
     * Returns the best-matching admin FAQ answer, or null.
     * Score = number of keyword words matched; branch-specific FAQs win ties.
     */
    private function matchFaq(string $input, array $ctx): ?string
    {
        $haystack = ' '.implode(' ', $this->words($input)).' ';
        $branchId = isset($ctx['branch']['id']) ? (int) $ctx['branch']['id'] : null;

        $best = null;
        $bestScore = 0.0;

        foreach ($ctx['faqs'] as $faq) {
            // ids can arrive as strings depending on the DB driver — compare as ints
            $faqBranch = isset($faq['branch_id']) ? (int) $faq['branch_id'] : null;
            if ($faqBranch !== null && $faqBranch !== $branchId) {
                continue;
            }

            $score = 0.0;
            foreach ($faq['keywords'] ?? [] as $keyword) {
                $kw = $this->words((string) $keyword);
                if ($kw && str_contains($haystack, ' '.implode(' ', $kw).' ')) {
                    $score += count($kw);
                }
            }
            if ($score === 0.0) {
                continue;
            }
            if ($faqBranch !== null) {
                $score += 0.5;
            }
            if ($score > $bestScore) {
                $answer = trim($this->loc($faq['answer'] ?? null));
                if ($answer !== '') {
                    $best = $answer;
                    $bestScore = $score;
                }
            }
        }

        return $best;
    }

    /* ───────────────────────── query parsing ───────────────────────── */

    private function parseQuery(string $t, array $msg, array $ctx): array
    {
        $q = [
            'tags' => [], 'category' => null, 'calMax' => null, 'proteinMin' => null,
            'priceMax' => null, 'sort' => null, 'featured' => false,
        ];

        foreach ($this->uniqueTags($ctx['items']) as $tag) {
            if ($this->tagMatches($t, $msg, $tag)) {
                $q['tags'][] = $tag;
            }
        }

        // Common dietary words count even if no dish at this branch carries the
        // tag — "vegan options" should get "no vegan dishes here", not "huh?".
        $have = array_map(fn ($tg) => implode(' ', $this->words($tg)), $q['tags']);
        foreach (self::DIET_TERMS as $term) {
            if (in_array($term, $have, true)) {
                continue;
            }
            if ($this->tagMatches($t, $msg, $term)) {
                $q['tags'][] = ucwords($term);
            }
        }

        $best = 0;
        foreach ($ctx['categories'] as $c) {
            $ws = $this->words($this->loc($c['name'] ?? null));
            if ($this->hasAll($msg, $ws) && count($ws) > $best) {
                $q['category'] = $c;
                $best = count($ws);
            }
        }

        $rest = $t;
        $take = function (string $re) use (&$rest): ?float {
            if (! preg_match($re, $rest, $m)) {
                return null;
            }
            $pos = strpos($rest, $m[0]);
            $rest = substr_replace($rest, ' ', $pos, strlen($m[0]));

            return (float) ($m[1] ?? 0);
        };

        $cal = $take('~\b(?:under|below|less than|fewer than|lower than|max|maximum|up to|within|at most)\s*(\d{2,4})\s*(?:k?cals?|calories?)\b~')
            ?? $take('~\b(\d{2,4})\s*(?:k?cals?|calories?)\s*(?:or\s*)?(?:less|fewer|max|under|below)\b~');
        $q['calMax'] = $cal !== null ? (int) $cal : null;

        $pro = $take('~\b(?:over|above|at least|more than|min|minimum|greater than|higher than)\s*(\d{1,3})\s*(?:g|gr|grams?)?\s*(?:of\s*)?protein\b~')
            ?? $take('~\bprotein\s*(?:of\s*)?(?:over|above|at least|more than)\s*(\d{1,3})\b~')
            ?? $take('~\b(\d{1,3})\s*(?:g|gr|grams?)\s*(?:of\s*)?protein\b~');
        $q['proteinMin'] = $pro !== null ? (int) $pro : null;

        $price = $take('~\b(?:under|below|less than|cheaper than|up to|within|max|maximum|at most)\s*\$?\s*(\d+(?:\.\d+)?)\b~')
            ?? $take('~\$\s*(\d+(?:\.\d+)?)\s*(?:or\s*)?(?:less|under|max)\b~');
        if ($price !== null && $price > 0 && $price <= 200) {
            $q['priceMax'] = $price;
        }

        if (preg_match('~\blow ?(?:er)? ?cal|\blight(?:er|est)?\b|\bdiet\b|\bhealth(?:y|ier|iest)\b|\bfewest cal|\bless cal|\bweight loss|\blose weight~', $t)) {
            $q['sort'] = 'cal-asc';
        } elseif (preg_match('~\bprotein\b|\bgym\b|\bmuscle|\bworkout~', $t)) {
            $q['sort'] = 'protein-desc';
        } elseif (preg_match('~\bcheap(?:er|est)?\b|\bbudget\b|\baffordable\b|\binexpensive\b|\blowest price|\bleast expensive|\bbest value~', $t)) {
            $q['sort'] = 'price-asc';
        } elseif (preg_match('~\bmost expensive|\bpriciest|\bpremium|\bluxur|\bsplurge|\bhighest price~', $t)) {
            $q['sort'] = 'price-desc';
        }

        $wantsFeatured = (bool) preg_match(
            '~\brecommend|\bsuggest(?!ion)|\bpopular|\bbest ?sellers?|\bfavou?rites?|\bmust ?try|\bfeatured|\bwhat should i (?:get|order|try|eat|have)|\bsurprise me|\bwhats good|\btop picks?~',
            $t,
        );

        if ($q['sort'] === null && $q['proteinMin'] !== null) {
            $q['sort'] = 'protein-desc';
        }
        if ($q['sort'] === null && $q['calMax'] !== null) {
            $q['sort'] = 'cal-asc';
        }
        if ($q['sort'] === null && $q['priceMax'] !== null) {
            $q['sort'] = 'price-asc';
        }

        $q['featured'] = $wantsFeatured && ! $q['tags'] && ! $q['category'] && $q['sort'] === null;

        return $q;
    }

    private function hasQuery(array $q): bool
    {
        return $q['tags'] || $q['category'] || $q['calMax'] !== null || $q['proteinMin'] !== null
            || $q['priceMax'] !== null || $q['sort'] !== null || $q['featured'];
    }

    private function applyQuery(array $q, array $items, bool $relaxNumbers = false): array
    {
        $out = array_values(array_filter($items, function ($it) use ($q, $relaxNumbers) {
            if ($q['category'] && (int) ($it['category_id'] ?? 0) !== (int) ($q['category']['id'] ?? -1)) {
                return false;
            }
            if ($q['tags']) {
                $names = [];
                foreach ($it['tags'] ?? [] as $tag) {
                    $names[implode(' ', $this->words($tag['name'] ?? ''))] = true;
                }
                foreach ($q['tags'] as $tg) {
                    if (! isset($names[implode(' ', $this->words($tg))])) {
                        return false;
                    }
                }
            }
            if (! $relaxNumbers) {
                if ($q['calMax'] !== null && ! (($it['calories'] ?? null) !== null && $it['calories'] <= $q['calMax'])) {
                    return false;
                }
                if ($q['proteinMin'] !== null && ! (($it['protein_grams'] ?? null) !== null && $it['protein_grams'] >= $q['proteinMin'])) {
                    return false;
                }
                if ($q['priceMax'] !== null && ! ((float) ($it['price'] ?? 0) <= $q['priceMax'])) {
                    return false;
                }
            }

            return true;
        }));

        if ($q['featured']) {
            $featured = array_values(array_filter($out, fn ($it) => ! empty($it['is_featured'])));
            $out = $featured ?: $out;
        }

        $num = fn ($v, $fallback) => $v === null ? $fallback : $v;
        match ($q['sort']) {
            'cal-asc' => usort($out, fn ($a, $b) => $num($a['calories'] ?? null, 1e9) <=> $num($b['calories'] ?? null, 1e9)),
            'protein-desc' => usort($out, fn ($a, $b) => $num($b['protein_grams'] ?? null, -1) <=> $num($a['protein_grams'] ?? null, -1)),
            'price-asc' => usort($out, fn ($a, $b) => (float) $a['price'] <=> (float) $b['price']),
            'price-desc' => usort($out, fn ($a, $b) => (float) $b['price'] <=> (float) $a['price']),
            default => null,
        };

        return $out;
    }

    private function describe(array $q): string
    {
        $bits = [];
        if ($q['tags']) {
            $bits[] = implode(' & ', array_map('mb_strtolower', $q['tags']));
        }
        if ($q['category']) {
            $bits[] = 'in '.$this->loc($q['category']['name'] ?? null);
        }
        if ($q['calMax'] !== null) {
            $bits[] = "under {$q['calMax']} cal";
        }
        if ($q['proteinMin'] !== null) {
            $bits[] = "{$q['proteinMin']}g+ protein";
        }
        if ($q['priceMax'] !== null) {
            $bits[] = 'under $'.$this->plain($q['priceMax']);
        }

        return implode(', ', $bits);
    }

    private function queryReply(array $q, array $ctx, string $t): array
    {
        $results = $this->applyQuery($q, $ctx['items']);
        $allergyNote = preg_match('~allerg|intoleran|celiac|coeliac~', $t)
            ? "\n\nTags are a guide only — please confirm allergies with the team before ordering."
            : '';
        $desc = $this->describe($q);

        if (! $results) {
            $base = $this->applyQuery($q, $ctx['items'], true);
            $hint = '';
            if ($base) {
                if ($q['calMax'] !== null) {
                    $withCal = array_values(array_filter($base, fn ($i) => ($i['calories'] ?? null) !== null));
                    usort($withCal, fn ($a, $b) => $a['calories'] <=> $b['calories']);
                    if ($withCal) {
                        $hint = " The lightest I have is {$this->loc($withCal[0]['name'])} at {$withCal[0]['calories']} cal.";
                    }
                } elseif ($q['proteinMin'] !== null) {
                    $withPro = array_values(array_filter($base, fn ($i) => ($i['protein_grams'] ?? null) !== null));
                    usort($withPro, fn ($a, $b) => $b['protein_grams'] <=> $a['protein_grams']);
                    if ($withPro) {
                        $hint = " The highest I have is {$this->loc($withPro[0]['name'])} with {$withPro[0]['protein_grams']}g.";
                    }
                } elseif ($q['priceMax'] !== null) {
                    usort($base, fn ($a, $b) => (float) $a['price'] <=> (float) $b['price']);
                    $hint = " The most affordable is {$this->loc($base[0]['name'])} at {$this->money($base[0]['price'])}.";
                }
            }

            return $this->r(
                "I couldn't find anything matching that".($desc ? " ({$desc})" : '')."{$this->at($ctx)}.{$hint}{$allergyNote}",
                ['chips' => ["What's on the menu?", 'What do you recommend?']],
            );
        }

        $shown = array_slice($results, 0, self::MAX_CARDS);
        $n = count($results);
        $s = count($shown);

        if ($q['featured']) {
            $intro = 'Here '.($s === 1 ? 'is our top pick' : 'are our top picks').$this->at($ctx).':';
        } elseif ($q['sort'] !== null && $desc === '') {
            $intro = 'Here '.($s === 1 ? 'is the' : "are the {$s}").' '.self::SORT_LABEL[$q['sort']].' '.($s === 1 ? 'dish' : 'dishes').$this->at($ctx).':';
        } else {
            $intro = "I found {$n} ".($n === 1 ? 'dish' : 'dishes').($desc ? " ({$desc})" : '').$this->at($ctx).'.'
                .($n > self::MAX_CARDS ? ' Here are the top '.self::MAX_CARDS.':' : '');
        }

        return $this->r($intro.$allergyNote, [
            'items' => $this->cards($shown),
            'chips' => ['Show full menu', 'Opening hours'],
            'actions' => $n > self::MAX_CARDS
                ? [['kind' => 'link', 'label' => "See all {$n} on the menu", 'to' => '/menu']]
                : [],
        ]);
    }

    /* ───────────────────────── item lookup ───────────────────────── */

    /** @return array{0: array[], 1: array[]} [full matches, partial matches] */
    private function matchItems(string $t, array $msg, array $ctx, int $wordCount): array
    {
        $blocked = [];
        foreach ($this->uniqueTags($ctx['items']) as $tg) {
            foreach ($this->words($tg) as $w) {
                $blocked[$w] = true;
            }
        }
        foreach ($ctx['categories'] as $c) {
            foreach ($this->words($this->loc($c['name'] ?? null)) as $w) {
                $blocked[$w] = true;
            }
        }
        $generic = array_flip(self::GENERIC);

        $full = [];
        $partial = [];

        foreach ($ctx['items'] as $it) {
            $name = $this->loc($it['name'] ?? null);
            $distinct = array_values(array_filter(
                $this->words($name),
                fn ($w) => ! isset($generic[$w]) && ! isset($blocked[$w]),
            ));

            if (! $distinct) {
                // Name made only of tag/category/generic words → require the whole name.
                if ($this->phrase($t, $this->normalize($name))) {
                    $full[] = $it;
                }

                continue;
            }

            $hit = count(array_filter($distinct, fn ($w) => isset($msg[$w])));
            if ($hit === count($distinct)) {
                $full[] = $it;
            } elseif ($hit >= 1 && ($hit / count($distinct) >= 0.5 || $wordCount <= 3)) {
                $partial[] = $it;
            }
        }

        return [$full, $partial];
    }

    private function itemDetail(array $item, string $t, array $ctx): array
    {
        $name = $this->loc($item['name'] ?? null);
        $desc = trim($this->loc($item['description'] ?? null));
        $price = $this->money($item['price'] ?? 0);

        $nutrition = implode(' and ', array_filter([
            ($item['calories'] ?? null) !== null ? "{$item['calories']} calories" : null,
            ($item['protein_grams'] ?? null) !== null ? "{$item['protein_grams']}g of protein" : null,
        ]));

        $asksNutrition = (bool) preg_match('~\bcal|\bkcal|nutrition|macro|protein~', $t);
        $asksPrice = (bool) preg_match('~\bprice|\bcost|how much|\bdollar|\$~', $t);

        $parts = [];
        if ($asksNutrition && $nutrition !== '') {
            $parts[] = "{$name} has {$nutrition}.";
        } elseif ($asksNutrition) {
            $parts[] = "I don't have nutrition info for {$name} yet.";
        }
        $parts[] = ($asksPrice || ! $asksNutrition)
            ? "{$name} is {$price}{$this->at($ctx)}."
            : "It's {$price}{$this->at($ctx)}.";
        if ($desc !== '' && ! $asksPrice && ! $asksNutrition) {
            $parts[] = preg_match('~[.!?]$~', $desc) ? $desc : "{$desc}.";
        }
        if (! $asksNutrition && $nutrition !== '') {
            $parts[] = "It has {$nutrition}.";
        }
        if (! empty($item['tags'])) {
            $parts[] = 'Tags: '.implode(', ', array_map(fn ($x) => $x['name'], $item['tags'])).'.';
        }

        return $this->r(implode(' ', $parts), [
            'items' => $this->cards([$item]),
            'chips' => ['What do you recommend?', 'Show full menu'],
            'actions' => [['kind' => 'link', 'label' => 'Open the menu', 'to' => '/menu']],
        ]);
    }

    /* ───────────────────────── reply builders ───────────────────────── */

    private function askBranch(array $ctx): array
    {
        if (! $ctx['branches']) {
            return $this->r("I can't reach the branch list right now. Please try again in a moment.");
        }

        return $this->r('Menu, prices and hours vary by location — which branch are you interested in?', [
            'actions' => $this->branchActions($ctx),
        ]);
    }

    private function branchActions(array $ctx): array
    {
        return array_map(
            fn ($b) => ['kind' => 'branch', 'label' => $this->branchName($b), 'slug' => $b['slug']],
            $ctx['branches'],
        );
    }

    private function branchesList(array $ctx): array
    {
        if (! $ctx['branches']) {
            return $this->r("I don't have any branches listed right now.");
        }

        $lines = array_map(function ($b) {
            $bits = implode(' · ', array_filter([$b['address'] ?? null, $b['phone'] ?? null]));

            return '• '.$this->branchName($b).($bits !== '' ? " — {$bits}" : '');
        }, $ctx['branches']);

        $currentSlug = $ctx['branch']['slug'] ?? null;
        $others = array_values(array_filter($ctx['branches'], fn ($b) => $b['slug'] !== $currentSlug));
        $n = count($ctx['branches']);

        return $this->r("We have {$n} branch".($n === 1 ? '' : 'es').":\n".implode("\n", $lines), [
            'actions' => array_map(
                fn ($b) => ['kind' => 'branch', 'label' => ($ctx['branch'] ? 'Switch to' : 'Choose').' '.$this->branchName($b), 'slug' => $b['slug']],
                $others,
            ),
        ]);
    }

    private function menuOverview(array $ctx): array
    {
        if (! $ctx['categories']) {
            return $this->r('The menu is still loading — try again in a second, or open the menu page.', [
                'actions' => [['kind' => 'link', 'label' => 'Open the menu', 'to' => '/menu']],
            ]);
        }

        $lines = array_map(function ($c) use ($ctx) {
            $n = count(array_filter($ctx['items'], fn ($it) => (int) ($it['category_id'] ?? 0) === (int) $c['id']));

            return '• '.$this->loc($c['name'] ?? null).($n ? " ({$n})" : '');
        }, $ctx['categories']);

        return $this->r("Here's what we serve{$this->at($ctx)}:\n".implode("\n", $lines)."\n\nTap a category to see the dishes.", [
            'chips' => array_map(fn ($c) => $this->loc($c['name'] ?? null), array_slice($ctx['categories'], 0, 4)),
            'actions' => [['kind' => 'link', 'label' => 'Open the full menu', 'to' => '/menu']],
        ]);
    }

    /* ───────────────────────── helpers ───────────────────────── */

    private function prepare(array $ctx): array
    {
        return [
            'branch' => $ctx['branch'] ?? null,
            'branches' => array_values($ctx['branches'] ?? []),
            'categories' => array_values($ctx['categories'] ?? []),
            'items' => array_values($ctx['items'] ?? []),
            'settings' => $ctx['settings'] ?? [],
            'faqs' => array_values($ctx['faqs'] ?? []),
        ];
    }

    private function finalize(array $r, array $ctx): array
    {
        if (! empty($r['starters'])) {
            $r['chips'] = $this->starterChips($ctx);
        }
        unset($r['starters']);

        return $r;
    }

    private function r(string $text, array $extra = []): array
    {
        return array_merge(
            ['text' => $text, 'items' => [], 'actions' => [], 'chips' => [], 'is_fallback' => false],
            $extra,
        );
    }

    /** Only what the chat card needs — keeps the payload small. */
    private function cards(array $items): array
    {
        return array_map(fn ($it) => [
            'id' => $it['id'],
            'name' => $it['name'],
            'description' => $it['description'] ?? null,
            'price' => $it['price'] ?? null,
            'image' => $it['image'] ?? null,
            'calories' => $it['calories'] ?? null,
            'protein_grams' => $it['protein_grams'] ?? null,
            'is_featured' => (bool) ($it['is_featured'] ?? false),
            'tags' => array_map(fn ($tg) => ['id' => $tg['id'], 'name' => $tg['name']], $it['tags'] ?? []),
        ], array_slice($items, 0, self::MAX_CARDS));
    }

    private function loc(mixed $v): string
    {
        if (is_array($v)) {
            return (string) ($v['en'] ?? '');
        }

        return (string) ($v ?? '');
    }

    private function branchName(?array $b): string
    {
        return $b ? $this->loc($b['name'] ?? null) : '';
    }

    private function at(array $ctx): string
    {
        return $ctx['branch'] ? ' at '.$this->branchName($ctx['branch']) : '';
    }

    private function setting(array $ctx, string $key, string $fallback): string
    {
        $v = $ctx['settings'][$key] ?? null;

        return is_string($v) && trim($v) !== '' ? $v : $fallback;
    }

    private function phone(array $ctx): string
    {
        return $this->setting($ctx, 'contact_phone', ($ctx['branch']['phone'] ?? null) ?: self::DEFAULTS['phone']);
    }

    private function callAction(string $phone): array
    {
        return ['kind' => 'href', 'label' => "Call {$phone}", 'href' => 'tel:'.preg_replace('~[^\d+]~', '', $phone)];
    }

    private function socialLinks(array $ctx): array
    {
        $raw = $ctx['settings']['social_links'] ?? null;
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $parsed = json_decode($raw, true);
        if (! is_array($parsed)) {
            return [];
        }

        $links = [];
        foreach ($parsed as $entry) {
            // supports both [{platform,url}] and [[{platform,url}]]
            $group = (is_array($entry) && isset($entry['url'])) ? [$entry] : (is_array($entry) ? $entry : []);
            foreach ($group as $l) {
                if (is_array($l) && ! empty($l['url']) && is_string($l['url'])) {
                    $links[] = ['platform' => (string) ($l['platform'] ?? 'Link'), 'url' => $l['url']];
                }
            }
        }

        return $links;
    }

    private function money(mixed $v): string
    {
        return '$'.number_format((float) $v, 2, '.', '');
    }

    /** 7.0 → "7", 7.5 → "7.5" (matches JS number printing). */
    private function plain(float|int $n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    }

    /** @return string[] unique tag display names across the given items */
    private function uniqueTags(array $items): array
    {
        $seen = [];
        foreach ($items as $it) {
            foreach ($it['tags'] ?? [] as $tag) {
                $key = implode(' ', $this->words($tag['name'] ?? ''));
                if ($key !== '' && ! isset($seen[$key])) {
                    $seen[$key] = $tag['name'];
                }
            }
        }

        return array_values($seen);
    }

    private function tagMatches(string $t, array $msg, string $tagName): bool
    {
        $ws = $this->words($tagName);
        if ($this->hasAll($msg, $ws)) {
            return true;
        }
        foreach (self::TAG_SYNONYMS[implode(' ', $ws)] ?? [] as $p) {
            if ($this->phrase($t, $p)) {
                return true;
            }
        }

        return false;
    }

    /* ───────────────────────── text utils ───────────────────────── */

    private function normalize(string $raw): string
    {
        $s = mb_strtolower($raw, 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_D) ?: $s;
        }
        $s = (string) preg_replace('~[\x{0300}-\x{036f}]~u', '', $s);
        $s = (string) preg_replace("~['’`]~u", '', $s);
        $s = str_replace(['<', '>'], [' under ', ' over '], $s);
        $s = (string) preg_replace('~[^a-z0-9$.\s]~u', ' ', $s);
        $s = (string) preg_replace('~\s+~', ' ', $s);

        return trim($s);
    }

    private function stem(string $w): string
    {
        return strlen($w) > 3 && str_ends_with($w, 's') && ! str_ends_with($w, 'ss') ? substr($w, 0, -1) : $w;
    }

    /** @return string[] */
    private function words(string $s): array
    {
        $parts = preg_split('~[^a-z0-9]+~', $this->normalize($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(fn ($w) => $this->stem($w), $parts);
    }

    private function phrase(string $t, string $p): bool
    {
        return $p !== '' && (bool) preg_match('~\b'.preg_quote($p, '~').'\b~', $t);
    }

    private function hasAll(array $msg, array $ws): bool
    {
        if (! $ws) {
            return false;
        }
        foreach ($ws as $w) {
            if (! isset($msg[$w])) {
                return false;
            }
        }

        return true;
    }
}
