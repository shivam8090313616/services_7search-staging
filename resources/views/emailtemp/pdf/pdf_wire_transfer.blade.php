<table style="width:100%;margin:0 auto; color: #666;font-family: 'Inter', sans-serif;font-size: 14px;font-weight: 400;line-height: 1.6em; background-color: #ffffff;border-collapse: collapse;" border="0">
  <tr>
    <td>
 <table  style="width:100%;padding: 0px;margin:0px;color: #666;font-family: 'Inter', sans-serif;font-size: 14px;font-weight: 400;line-height: 1.6em;border-collapse: collapse;" border="0"> 
   <tr style="background-image: url(https://services.7searchppc.com/public/images/mail/topbg7.png);background-repeat: no-repeat; height:130px;">
   
     <td style="position: relative;text-align:left;min-height:23px; padding:25px 30px; font-weight:bold;color: #fff; text-transform: uppercase;font-size:56px;line-height: 1em; letter-spacing: 4px;">
         Invoice
     </td>
   <td style="padding: 25px 30px;   text-align: right;"> 
         <div class="tm_logo"><img src="https://services.7searchppc.com/public/images/mail/logo.png" alt="Logo"></div>	 
     </td> 
   </tr>
  
   <tr>
  <td colspan="2">
  <table style="width:100%; border-collapse: collapse; border-bottom:2px solid #E4E7EF;" border="0">
    <tr>				
      <td style="padding:15px 16px 15px 6px;text-align: left;">
        <p style="font-size:15px;color:#929292;margin:0;">Invoice Number</p>
        <p style="font-size:18px;color:#282828;margin:10px 0 5px;font-weight:600;">#{{ $details['transaction_id'] }}</p>
      </td>	 
      <td style="padding:15px 16px;text-align: left;">
        <p style="font-size:15px;color:#929292;margin:0;">Date</p>
        <p style="font-size:18px;color:#282828;margin:10px 0 5px;font-weight:600;"><?php echo date('d-m-Y', strtotime($details['createdat']))?></p>
      </td>
      <td style="padding:15px 16px;text-align: left;">
        <p style="font-size:15px;color:#929292;margin:0;">Payment Method</p>
        <p style="font-size:18px;color:#282828;margin:10px 0 5px;font-weight:600;">Wire Transfer</p>
      </td>	
      <td style="padding:15px 16px;text-align: left;">
        <p style="font-size:15px;color:#929292;margin:0;">GSTN</p>
        <p style="font-size:18px;color:#282828;margin:10px 0 5px;font-weight:600;">{{$gst_no}}</p>
      </td>
    </tr>
   
  </table>
   </td>     
   </tr>  
   <tr>
     <td style="text-align:left; padding-top: 20px; vertical-align: top;"> 
     <p style="color: #929292;font-size:16px;margin:0 0 5px;">Bill to:</p>	  
     <?php if(strlen($legal_entity)){ ?>
      <p style="color: #282828;margin:0 0 5px;"> <b style="font-size:22px;"> {{$legal_entity}}</b> <span style="font-size:16px;">({{$name}})</span></p>
      <?php } else{ ?>
        <p style="color: #282828;font-size:22px;margin:0 0 5px;"> {{ $name }}</p>
     <?php } ?>
     <p style="color: #282828;font-size:15px;margin:0 0 5px;">  {{ $details['addressline1'] }}, {{ $details['city'] }}, {{ $details['state'] }}, {{ $details['country'] }} <?php if(strlen($pin) > 0) { echo '('.$pin.')'; } ?></p>
     <p style="color: #282828;font-size:15px;margin:0 0 5px;"> {{ $details['emails'] }} |  +{{ $phonecode }}-{{ $details['phone'] }}</p>
       
     </td>
     <td style="text-align:left; padding-top: 20px; vertical-align: top;padding-left:120px;">		 
<p style="color: #929292;font-size:16px;margin:0 0 5px;"> Bill from:  </p>
<p style="color: #282828;font-size:22px;margin:0 0 5px;"> 7SearchPPC.com</p>
<p style="color: #282828;font-size:15px;margin:0 0 5px;"> contact@7searchppc.com</p>
<p style="color: #282828;font-size:15px;margin:0 0 5px;"> <b>GSTN:</b> 09AAECL2613D1ZW</p>
<p style="color: #282828;font-size:15px;margin:0 0 5px;">(LOGELITE PRIVATE LIMITED)</p>
     </td>
   </tr>
     <tr>
     <td colspan="2" style="padding-top: 20px;">	

  <table style="width:100%; border-collapse: collapse; border-bottom:2px solid #3B82F6;" border="0">
    <thead>
      <tr style="border-top:2px solid #3B82F6; color: #929292;text-align: left;">
         <th style="padding: 10px 15px;line-height: 1.55em;text-align: left; font-size:16px;font-weight: normal;">Description</th>
         <th style="padding: 10px 15px;line-height: 1.55em;text-align: center; font-size:16px;font-weight: normal;">Quantity</th>
         <th style="padding: 10px 15px;line-height: 1.55em;text-align: center; font-size:16px;font-weight: normal;">SAC</th>
         <th style="padding: 10px 15px;line-height: 1.55em;text-align: right; font-size:16px;font-weight: normal;">Amount</th>
         <!--<th style="padding: 10px 15px;line-height: 1.55em;text-align: right; font-size:16px;font-weight: normal;">Total</th>-->
      </tr>
    </thead>
    <tbody>
       <tr>
               <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #666;text-align:left;"><?php if(!empty($details['remark'])){ echo $details['remark']; } else{ echo 'Payable amount towards 7Search PPC.'; } ?></td>
               <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #666;text-align:center;">1</td>
               <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #666;text-align:center;">998365</td>
               <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #666;text-align:right;">${{number_format($details['amount'], 2, '.', ',')}}</td>
       <!--<td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #666;text-align">${{ number_format($details['amount'], 2, '.', ',') }}</td>-->
             </tr>
    </tbody>
  </table>	
          
     </td>   
   </tr>
<tr>
     <td  style="padding-top: 10px; padding-bottom:50px;">	
        <p style="font-size:14px;color:#929292;margin:0 0 5px;">Amount in words:</p>
    <p style="font-size:16px;color:#282828;margin:0 0 35px;">{{ucwords($wordAmount)}} Dollars Only</p>
    
          <p style="font-size:14px;color:#929292;margin:0 0 5px;">Details for wire transfer:</p>
    <p style="font-size:17px;color:#282828;margin:0 0 5px;"><span style="font-size:15px;color:#929292">Account Holder’s Name: </span>{{$acc_name}} </p>
          <p style="font-size:17px;color:#282828;margin:0 0 5px;"><span style="font-size:15px;color:#929292"> Bank Name:</span> {{$bank_name}}</p>
          <p style="font-size:17px;color:#282828;margin:0 0 5px;"><span style="font-size:15px;color:#929292"> Bank Account No.:</span> {{$acc_number}}</p>
          <p style="font-size:17px;color:#282828;margin:0 0 5px;"><span style="font-size:15px;color:#929292">Swift/BIC Code:</span> {{$swift_code}}</p>
          <p style="font-size:17px;color:#282828;margin:0 0 5px;"><span style="font-size:15px;color:#929292">IFSC Code:</span> {{$ifsc_code}}</p>
          <p style="font-size:17px;color:#282828;margin:0 0 5px;"><span style="font-size:15px;color:#929292">Address:</span> {{$acc_address}}, {{$country}} </p>
   </td>   
     <td  style="padding-top:10px;padding-bottom:50px;vertical-align: top;">	 
     
  <table style="width:100%; border-collapse: collapse; border-bottom:2px solid #3B82F6; " border="0">
      <?php if(@$download != 1): ?>
    <thead>
      <tr>
        <td style="padding: 10px 15px; color:#282828;text-align: left;">Processing Fee</td> 
        <td style="padding: 10px 15px; color:#282828;text-align: right;">${{ number_format($details['fee'], 2, '.', ',') }}</td>
      </tr>
    	<tr>
    		 <th style="border-top:1px solid #E4E7EF;padding: 10px 15px;line-height: 1.55em;text-align: left; font-size:16px;font-weight: 600;color: #282828;">Subtotal</th>					 
    		 <th style="border-top:1px solid #E4E7EF;padding: 10px 15px;line-height: 1.55em;text-align: right; font-size:16px;font-weight: 600;color: #282828;">${{ number_format($details['amount'] + $details['fee'], 2, '.', ',') }}</th>
    	</tr>
    </thead>
      <?php endif; ?>
    <tbody>
         <?php if(@$download != 1): ?>
      <tr>
      	<td style="border-top:1px solid #E4E7EF;padding: 10px 15px; color:#282828;text-align: left;">Total Tax</td> 
      	<td style="border-top:1px solid #E4E7EF; padding: 10px 15px; color:#282828;text-align: right;">${{ number_format($details['gst'], 2, '.', ',') }}</td>
      </tr>
       <?php endif; ?>
      <tr>
       <td style="<?php echo @$download == 1 ? 'padding: 10px 15px; color:#282828;font-weight: 600;text-align: left;' : 'border-top:2px solid #3B82F6;padding: 10px 15px; color:#282828;font-weight: 600;text-align: left;'; ?>">
    Grand Total
    </td>
        <td style="<?php echo @$download == 1 ? 'padding: 10px 15px; color:#282828;font-weight: 600;text-align: right;' : 'border-top:2px solid #3B82F6;padding: 10px 15px; color:#282828;font-weight: 600;text-align: right;'; ?>">${{ number_format($details['payble_amt'], 2, '.', ',') }}</td>
      </tr>
    </tbody>
  </table>	
     </td>   
  </tr>
 
   <tr>
     <td colspan="2" style="height:20px;border-top: 2px solid #dbdfea;"> </td>     
   </tr>
  <tr>
     <td colspan="2" style="color: #111;text-align: left;font-style: normal;">
        
         <p style="color:#929292">Terms &amp; Conditions:</p>
         <ul>
             <?php foreach ($termslist as $value): ?>
                 <li><?=$value->terms;?></li>
             <?php endforeach; ?>
         </ul>
     </td>
   </tr>
   <tr>
     <td colspan="2" style="height:20px;border-top: 2px solid #dbdfea;"> </td>     
   </tr>
<tr>
     <td colspan="2">		 
         <table  style="width:100%; border-collapse: collapse; margin: 0 auto;" border="0">     
           <tbody>
             <tr>
               <td style="margin:0;padding:0;"><img src="https://services.7searchppc.com/public/images/mail/logofooter.png" alt="Logo"></td>
               <td style="margin:0;padding:0 0 0 10px;"><p style="width: 16px;height:80px;border-left: 2px solid #3B82F6;"></p></td>
               <td>
        <p style="color:#282828 ;font-weight: 700;font-size:25px;margin: 0;padding: 0;line-height: 35px;">Thank you for doing business with us.</p>
        <p style="color:#929292;font-size:18px;margin: 0;padding:5px 0 0; ">We look forward to working with you again.</p> 
      </td>
             </tr>
            
           </tbody>
         </table>		 
     </td>   
   </tr>


 </table>
     </td>
   </tr> 
 </table>
 