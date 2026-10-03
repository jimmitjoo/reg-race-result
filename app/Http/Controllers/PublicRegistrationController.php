<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use Illuminate\View\View;

class PublicRegistrationController extends Controller
{
    public function show(string $organizer, string $event, Registration $registration): View
    {
        $registration->load('raceClass.event.organizer');
        $registrationEvent = $registration->raceClass->event;
        abort_unless($registrationEvent->slug === $event && $registrationEvent->organizer->slug === $organizer, 404);

        return view('public.registration', ['registration' => $registration, 'event' => $registrationEvent]);
    }
}
