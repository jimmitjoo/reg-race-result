<?php

return [
    'title' => 'Betalningar',
    'help' => 'Anmälningsavgifterna betalas direkt till klubbens eget Stripe-konto och betalas ut till klubbens bankkonto. Klubben sköter själv återbetalningar och tvister i Stripe.',
    'none' => 'Inget Stripe-konto är kopplat. Anmälan på nätet till betalda lopp öppnar när kontot kan ta emot betalningar.',
    'incomplete' => 'Stripe behöver mer information innan kontot kan ta emot betalningar.',
    'ready' => 'Kopplat: kontot kan ta emot betalningar.',
    'connect' => 'Koppla Stripe',
    'continue' => 'Fortsätt i Stripe',
    'fee' => 'Plattformsavgift per anmälan: :fee',
    'no_fee' => 'ingen',
    'example' => 'Exempel: en anmälan för :price betald med ett vanligt kort ger Stripe cirka :stripe och plattformen :platform. Klubben får :club.',
    'responsibility' => 'Stripes avgifter, återbetalningar och tvister dras från klubbens Stripe-konto. Stripes avgift kommer inte tillbaka vid återbetalning. Återbetalar ni hela beloppet i Stripe tas löparen bort från startlistan här.',
];
