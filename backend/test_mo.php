<?php
$sc = App\Modules\Messaging\Models\Shortcode::firstOrCreate(
    ['shortcode' => '22344'],
    ['is_dedicated' => true, 'client_account_id' => 1]
);
App\Modules\Messaging\Models\Keyword::firstOrCreate(
    ['shortcode_id' => $sc->id, 'keyword' => 'JOIN'],
    ['action_type' => 'OPT_IN', 'reply_message' => 'Welcome to Casamoko!']
);
echo "Created shortcode and keyword.\n";
