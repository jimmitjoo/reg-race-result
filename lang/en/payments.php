<?php

return [
    'title' => 'Payments',
    'help' => 'Registration fees are paid straight to the club\'s own Stripe account and paid out to the club\'s bank account. The club handles refunds and disputes in Stripe itself.',
    'none' => 'No Stripe account is connected. Online registration for paid races opens when the account can take payments.',
    'incomplete' => 'Stripe needs more information before the account can take payments.',
    'ready' => 'Connected: the account can take payments.',
    'connect' => 'Connect Stripe',
    'continue' => 'Continue in Stripe',
    'fee' => 'Platform fee per registration: :fee',
    'no_fee' => 'none',
    'example' => 'Example: a registration of :price paid with a standard card gives Stripe about :stripe and the platform :platform. The club gets :club.',
    'responsibility' => 'Stripe fees, refunds and disputes are taken from the club\'s Stripe account. Stripe\'s fee is not returned on a refund. If you refund the full amount in Stripe, the runner is removed from the start list here.',
];
