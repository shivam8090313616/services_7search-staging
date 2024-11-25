<!DOCTYPE html>
<html lang="en">
<head>
  <title> Download User Report List </title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</head>
<body>

     <table  style="width:100%">
        <tr>
            <td> <img src="http://192.168.18.166/7sapp/public/images/logo/logo.png">  </td>

             <td width="10%"> Export Date </td>
             <td width="10%" > 12.09.2022 </td>
        </tr>
     </table>

           <table class="table table-bordered" style="width:100%">
                <thead>
                      <tr>
                        <th colspan="7" style="text-align:center; background:#cbeb67;"> <b>  7 Search PPC  </b></th>
                    </tr>
                    <tr>
                        <th>Sr.No.</th>
                        <th>User Name</th>
                        <th>User ID </th>
                        <th>Email</th>
                        <th>Phone No.</th>
                        <th>User Type</th>
                        <th>Wallet</th>
                      
                    </tr>
                </thead>
                <tbody>
                    <?php  $i='1'; foreach ($row as $value) { ?>
                    <tr>
                        <td>{{ $i++ }}</td>
                        <td>{{ $value->name }}</td>
                        <td>{{ $value->uid }}</td>
                        <td>{{ $value->email }}</td>
                        <td>{{ $value->phone }}</td>
                        <td>{{ $value->user_type }}</td>
                        <td><b>${{ $value->wallet }} </b></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
       