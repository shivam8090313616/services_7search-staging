<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <title>7SearchPPC - Payment </title>
  <meta name="description" content="">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
 
</head>
<body>
  <center>
  <h2 style="margin-top:50px;">Payment Processing</h2>
</center>
	<form action="https://payments.airpay.co.in/pay/index.php" method="post">
            <input type="hidden" name="privatekey" value="<?php echo $privatekey; ?>">
            <input type="hidden" name="mercid" value="292774">
				    <input type="hidden" name="orderid" value="<?php echo $orderid; ?>">
 		        <input type="hidden" name="currency" value="<?php echo $currency; ?>">
		        <input type="hidden" name="isocurrency" value="<?php echo $isocurrency; ?>">
		        <input type="hidden" name="buyerEmail" value="<?php echo $buyerEmail; ?>">
		        <input type="hidden" name="buyerFirstName" value="<?php echo $buyerFirstName; ?>">
		        <input type="hidden" name="buyerLastName" value="<?php echo $buyerLastName; ?>">
		        <input type="hidden" name="amount" value="<?php echo $amount; ?>">
		        <input type="hidden" name="buyerCountry" value="<?php echo $buyerCountry; ?>">
		        <input type="hidden" name="checksum" value="<?php echo $checksum; ?>">
				    <input type="hidden" name="chmod" value="<?php echo $hiddenmod; ?>">
			</form>
  <script>
      $('form').submit();
  </script>
</body>
</html>
