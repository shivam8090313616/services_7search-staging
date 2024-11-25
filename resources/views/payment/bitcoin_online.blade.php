<div class="col-lg-12">
	<div class="text-right">
	  <form action="https://www.coinpayments.net/index.php" method="post">
		<input type="hidden" name="cmd" value="_pay">
		<input type="hidden" name="reset" value="1">
		<!-- <input type="hidden" name="merchant" value="0a70094b961261b1bdf613b40a00f6dd"> -->
		<input type="hidden" name="merchant" value="236a3313aed9da791fb759e828dcd1fe">
		<input type="hidden" name="item_name" value="Add to Wallet">
		<input type="hidden" name="currency" value="USD">
        <input type="hidden" name="invoice" value="<?php echo $txnid; ?>">
		<input type="hidden" name="amountf" value="<?php echo $amounts; ?>">
		<input type="hidden" name="quantity" value="1">
		<input type="hidden" name="allow_quantity" value="0">
		<input type="hidden" name="want_shipping" value="0">
		<input type="hidden" name="success_url" value="<?php echo $res_url; ?>">
		<input type="hidden" name="cancel_url" value="<?php echo $res_url; ?>">
		<input type="hidden" name="ipn_url" value="<?php echo $res_url; ?>">
		<input type="hidden" name="allow_extra" value="1">
		<a href="#" class="btn btn-danger pull-left">Cancel</a>
	  </form>
	</div>
 </div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
    $('form').submit();
</script>
