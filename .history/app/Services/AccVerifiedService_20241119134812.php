<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationEmail;

class AccVerifiedService
{
    /**
     * Handle account verification or send verification email.
     *
     * @param string $uid
     * @param string|null $method
     * @return array
     */
    public function handleVerification(string $uid, ?string $method = null): array
    {
        // Find the user by UID
        $user = User::where('uid', $uid)->first();

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }

     

        // If no method is provided, return ac_verified status
        // if (is_null($method)) {
        //     return [
        //         'success' => true,
        //         'ac_verified' => $user->ac_verified,
        //     ];
        // }
        
        // If method is 'sendMail', send verification email
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

        // If the method is invalid, return an error
        return [
            'success' => false,
            'message' => 'Invalid method provided.',
        ];
    }

    /**
     * Send a verification email to the user.
     *
     * @param User $user
     * @return bool
     */
    protected function sendVerificationEmail(User $user): bool
    {
        try {
            $user = User::select('email', 'first_name', 'last_name')->where('uid', $uid)->first();
         $email = $user->email;
         $fullname = $user->first_name . ' ' . $user->last_name;
 
         $subject = "If You veriefied then will create and update vcampaign"
 
         $data['details'] = array(
             'subject' => $subject,
             'fullname' => $fullname,
             'usersid' => $campaign->advertiser_code,
             'campaignid' => $campaign->campaign_id,
             'campaignname' => $campaign->campaign_name,
             'campaignadtype' => $campaign->ad_type
         );
 
         $body = View('emailtemp.campaigncreate', $data);
         sendmailUser($subject, $body, $email);
            return true;
        } catch (\Exception $e) {

            return false;
        }
    }
}
