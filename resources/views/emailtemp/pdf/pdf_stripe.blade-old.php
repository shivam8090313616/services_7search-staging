<table style="width:100%; color: #666;font-family: 'Inter', sans-serif;font-size: 14px;font-weight: 400;line-height: 1.6em; background-color: #ffffff;border-collapse: collapse;" border="0">
    <tr>
      <td>
   <table  style="width:100%;padding: 0px;margin:0px;color: #666;font-family: 'Inter', sans-serif;font-size: 14px;font-weight: 400;line-height: 1.6em;border-collapse: collapse;" border="0"> 
     <tr>
       <td> 
           <div class="tm_logo"><img src="https://www.7searchppc.com/assets/images/logo.png" alt="Logo"></div>	 
       </td>  
       <td style="position: relative;text-align:right;background:#fff;min-height: 18px; padding:25px 30px; color: #fff; text-transform: uppercase;font-size: 50px;line-height: 1em; background-image: url(https://services.7searchppc.com/public/images/mail/topbg.png);background-repeat: no-repeat;right: -24px;">
           Invoice
       </td>
     </tr>
    <tr>
       <td colspan="2" style="height:20px;"> </td>     
     </tr>
     <tr>
       <td  style="text-align: left;">
        <b style="color: #000;">Payment Method: </b>{{ $details['payment_mode'] }}
       </td>
       <td style="position: relative;text-align:right;background-color: #fff;min-height: 18px; padding:5px 20px;background-image: url(https://services.7searchppc.com/public/images/mail/topbg.png);background-repeat: no-repeat;right: -24px; ">
        <span style="color: #fff;">Invoice No: <b>{{ $details['transaction_id'] }}</b> Date: <b><?php echo date('d-m-Y', strtotime($details['createdat']))?></b></span>	
       </td>
     </tr>  
     <tr>
       <td style="text-align:left; padding-top: 20px;"> 
         <p><b style="color: #000;">Invoice To:</b></br>	  
        {{ $details['full_name'] }} <br>
             {{ $details['addressline1'] }}  <br> {{ $details['city'] }} <br>
        {{ $details['emails'] }}<br>
        {{ $details['phone'] }}
         </p> 
       </td>
       <td style="text-align:right; padding-top: 20px;">		 
             <p><b style="color: #000;">Pay To:</b></br> 
               7SearchPPC.com <br>
               contact@7searchppc.com
             </p>
             <p><b style="color: #000;">GSTIN:</b>
               09AAECL2613D1ZW <br>
               (LOGELITE PRIVATE LIMITED)
             </p>
       </td>
       
     </tr>
       <tr>
       <td colspan="2">		 
           <table  style="width:100%; border-collapse: collapse;" border="0">
             <thead>
               <tr style="background-color: #3B82F6;color: #fff;font-weight: 600;text-align: left;">
                 <th style="padding: 10px 15px;line-height: 1.55em;text-align: left;">DESCRIPTION</th>
                 <th style="padding: 10px 15px;line-height: 1.55em;text-align: left;">QUANTITY</th>
                 <th style="padding: 10px 15px;line-height: 1.55em;text-align: left;">SAC</th>
                 <th style="padding: 10px 15px;line-height: 1.55em;text-align: left;">PRICE</th>
                 
               </tr>
             </thead>
             <tbody>
               <tr>
                 <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #666;">{{ $details['remark'] }}</td>
                 <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #666;">1</td>
                 <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #666;">998365</td>
                 <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #666;">${{ $details['amount'] }}</td>
               </tr>
               <tr>
                <td colspan="2"></td>
                <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #111;background:#f5f6fa;font-weight: 700;">Subtotal</td>
                <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #111;background:#f5f6fa;font-weight: 700;">${{ $details['amount'] }}</td>
              </tr>
              <tr>
                <td colspan="2"></td>
                <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #666;background:#f5f6fa;">Processing fee </td>
                <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #666;background:#f5f6fa;">${{ $details['fee'] }}</td>
              </tr>
              <tr>
                <td colspan="2"></td>
                <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #666;background:#f5f6fa;">Tax </td>
                <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #666;background:#f5f6fa;">${{ $details['gst'] }}</td>
              </tr>
              <tr>
                <td colspan="2"></td>
                <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #fff;font-weight: 700; background-color: #3B82F6;">Grand Total </td>
                <td style="padding: 10px 15px;line-height: 1.55em;caption-side: bottom;border-collapse: collapse;border-top: 1px solid #dbdfea;color: #fff;font-weight: 700; background-color: #3B82F6;">${{ $details['payble_amt'] }}</td>
              </tr>
               
                   
       
             </tbody>
           </table>		 
       </td>   
     </tr>
     <tr>
       <td colspan="2" style="height:30px;"> </td>     
     </tr>
       <tr>
       <td colspan="2" style="color: #111;margin-top: 30px;text-align: left;font-style: normal;">
           <hr >
           <p><b>Terms &amp; Conditions:</b></p>
           <ul>
           <li>This transaction is governed by the terms and conditions agreed upon by the user at the time of making the payment. </li>
           <li>The amount, once added, can only be used for running ad campaigns on 7SearchPPC. </li>
           <li>The amount, once added, cannot be refunded to the user’s bank account or any other personal wallet. </li>
           <li>In case some amount of money has been debited from the wallet accidentally without the user’s consent, it will be transferred back to the wallet within a few hours of a claim.</li>
           <li>Users will never be charged more than the required amount to run the campaign.</li>
           <li>The amount added to the wallet will be calculated as per the USD.</li>
           </ul>
       </td>
     </tr>
    
   </table>
       </td>
     </tr> 
   </table>
   