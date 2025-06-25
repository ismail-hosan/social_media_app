<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaLink;
use App\Traits\apiresponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SocalMediaLinkController extends Controller
{
    use apiresponse;

    private $socalMedia;

    public function __construct(SocialMediaLink $socialMediaLink)
    {
        $this->socalMedia = $socialMediaLink;
    }
    public function index()
    {
        $user_id = auth()->id();
        $data = $this->socalMedia->where('user_id', $user_id)->select('social_media_type', 'url')->get();

        return $this->success($data, 'Data Fetch Successfully!', 200);
    }
    public function store(Request $request)
    {
        $userId = auth()->id();

        $validatedData = $request->validate([
            'social_media_type' => [
                'required',
                'string',
                Rule::in(['facebook', 'twitter', 'instagram', 'linkedin', 'youtube']),
            ],
            'url' => ['required', 'url', 'max:255'],
        ]);

        // Check if the social media link for this user + type exists
        $existingLink = $this->socalMedia
            ->where('user_id', $userId)
            ->where('social_media_type', $validatedData['social_media_type'])
            ->first();

        if ($existingLink) {
            // Update the existing record
            $existingLink->update([
                'url' => $validatedData['url'],
            ]);

            return $this->success($existingLink, 'Social media link updated successfully!', 200);
        } else {
            // Create new record
            $newLink = $this->socalMedia->create([
                'user_id' => $userId,
                'social_media_type' => $validatedData['social_media_type'],
                'url' => $validatedData['url'],
            ]);

            return $this->success($newLink, 'Social media link added successfully!', 200);
        }
    }
}
