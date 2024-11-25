<?php
$req_method = getenv('REQUEST_METHOD');
if ($req_method !== 'POST') {
  http_response_code(404);
  echo "<p>Invalid request!</p>";
  exit();
}
$custCurrency = $currencycode;
$customerEmail = $user->email;
$customerId = $user->uid;
$getUsers = \App\Models\User::where('uid', $customerId)->first();
$name = $getUsers->first_name .' '. $getUsers->last_name;
$address = $getUsers->first_name;
$amt_usd = trim($amounts); 
$stripe = new \Stripe\StripeClient($key);
  $checkout_session = $stripe->checkout->sessions->create([
    'customer_email' => $customerEmail,
    //'billing_address_collection' => 'auto',
    'billing_address_collection' => 'required',
    'payment_method_types' => ['card'],
    'shipping_address_collection' => [
            'allowed_countries' => ['IN'],
        ],
      'metadata' => [
      'order_id' => $txnids, 
      'request_for' => $request_for,
      'username' => $name,
      'address' => $getUsers->address_line1,
      'address_line1' => $getUsers->address_line1,
      'address_line2' => $getUsers->address_line2,
      'postal_code' => $getUsers->post_code,
      'city' => $getUsers->city,
      'state' => $getUsers->state,
      'country' => $getUsers->country,
    ],
    'line_items' => [[
      'price_data' => [
        'currency' => $custCurrency,
        'unit_amount' => $amounts,
        'product_data' => [
          'name' => '7Search PPC Service',
          'images' => ["https://www.7searchppc.com/assets/images/logo/7searchppc-logo.png"],
        ],
      ],
      'quantity' => 1,
    ]],
    'mode' => 'payment',
    'success_url' => "https://services.7searchppc.in/app_stripe_success/{CHECKOUT_SESSION_ID}",
    'cancel_url' => "https://services.7searchppc.in/razorpay/failed",
  ]);
return redirect()->to($checkout_session->url)->send();
?>