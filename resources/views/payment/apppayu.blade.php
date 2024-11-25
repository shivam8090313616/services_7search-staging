<?php


// Merchant Key and Salt as provided by Payu.
$mkey = "wTDbEv8C";
$salt = "4H8ohh9Tll";
$PAYU_BASE_URL = "https://sandboxsecure.payu.in";    // For Sandbox Mode

//  $PAYU_BASE_URL = "https://secure.payu.in";      // For Production Mode
$amt = $amounts;
$fname = $user->full_name;
$email = $user->email;
$phone = $user->phone;
$tid = $txnids;

$action = '';
$formError = 0;
$txnid  = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
$hash   = hash('sha512', $mkey.'|'.$txnid.'|'.$amt.'|AddToWallet|'.$fname.'|'.$email.'|'.$tid.'|'.$request_for.'|||||||||'.$salt);
$action = $PAYU_BASE_URL . '/_payment';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <title>7SearchPPC - Payment </title>
  <meta name="description" content="">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script>
    var hash = '<?php echo $hash ?>';
    function submitPayuForm() {
      if(hash == '') {
        return;
      }
      var payuForm = document.forms.payuForm;
      payuForm.submit();
    }
  </script>
</head>
<body>
  <center>
  <h2 style="margin-top:50px;">Payment Processing</h2>
</center>

<form action="<?php echo $action; ?>" method="post" name="payuForm">
  <input type="hidden" name="key" value="<?php echo $mkey ?>" />
  <input type="hidden" name="hash" value="<?php echo $hash ?>"/>
  <input type="hidden" name="txnid" value="<?php echo $txnid ?>" />

    <input type="hidden" name="amount" value="<?=($amt) ? $amt : 10; ?>" />
    <input type="hidden" name="firstname" id="firstname" value="<?=$fname?>" />
    <input type="hidden" name="email" id="email" value="<?=(strlen(trim($email)) > 0) ? $email : 'info@kplay.in'; ?>" />
    <input type="hidden" name="phone"  value="<?=$phone?>"  />
    <input type="hidden" name="productinfo" value="AddToWallet" />
    <input type="hidden" name="udf1" value="<?=$tid?>" />
  	<input type="hidden" name="udf2" value="<?=$request_for?>" />
    <input type="hidden" name="surl" value="https://services.7searchppc.in/app_payment_success" size="64" />
    <input type="hidden" name="furl" value="https://services.7searchppc.in/razorpay/failed" size="64" />
    <input type="hidden" type="hidden" name="service_provider" value="payu_paisa" size="64" /> 
      <?php if(!$hash) { ?>
      <?php } ?>

</form>
  <script>
    $('form').submit();
  </script>
</body>
</html>
