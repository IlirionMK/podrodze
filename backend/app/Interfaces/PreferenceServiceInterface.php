<?php

namespace App\Interfaces;

use App\DTO\Preference\Preference;
use Illuminate\Http\Request;

interface PreferenceServiceInterface
{
    public function getPreferences(Request $request): Preference;
    public function updatePreferences(Request $request): array;
}
