<?php

namespace MYVH\Lifecycle\WordPress;

use MYVH\Lifecycle\Contracts\RewriteRulesInterface;

class WordPressRewriteRules implements RewriteRulesInterface {
    public function flush(): void {
        flush_rewrite_rules();
    }
}
