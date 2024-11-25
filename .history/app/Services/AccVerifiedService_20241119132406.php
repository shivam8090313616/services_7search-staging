<?php

namespace App\Services;

use App\Models\User;

class AccVerifiedService
{
    public function handleVerification(string $uid, ?string $method = null): array
    {
        $user = User::where('uid', $uid)->first();

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }

        if (is_null($method)) {
            return [
                'success' => true,
                'ac_verified' => $user->ac_verified,
            ];
        }

        if ($method === 'sendMail') {
            $mailStatus = $this->sendVerificationEmail($user);

            if ($mailStatus) {
                return [
                    'success' => true,
                    'message' => 'Verification email sent successfully.',
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send verification email.',
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Invalid method provided.',
        ];
    }

    protected function sendVerificationEmail(User $user): bool
    {
        try {
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
