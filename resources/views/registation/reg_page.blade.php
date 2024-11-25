<style type="text/css">
	.divider-text {
    position: relative;
    text-align: center;
    margin-top: 15px;
    margin-bottom: 15px;
}
.divider-text span {
    padding: 7px;
    font-size: 12px;
    position: relative;   
    z-index: 2;
}
.divider-text:after {
    content: "";
    position: absolute;  
    width: 100%;
    border-bottom: 1px solid #ddd;
    top: 55%;
    left: 0;
    z-index: 1;
}

.btn-facebook {
    background-color: #405D9D;
    color: #fff;
}
.btn-twitter {
    background-color: #42AEEC;
    color: #fff;
}
</style>
<link href="//maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
<!------ Include the above in your HEAD tag ---------->
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.8/css/all.css">

<div class="container">
<div class="card bg-light">
<article class="card-body mx-auto" style="max-width: 400px;">
	<h4 class="card-title mt-3 text-center">Create Account</h4>
	<!-- <p class="text-center">Get started with your free account</p>
	<p>
		<a href="" class="btn btn-block btn-twitter"> <i class="fab fa-twitter"></i>   Login via Twitter</a>
		<a href="" class="btn btn-block btn-facebook"> <i class="fab fa-facebook-f"></i>   Login via facebook</a>
	</p>
	<p class="divider-text">
        <span class="bg-light">OR</span>
    </p> -->
      <div class="form-group input-group">
      <div class="input-group-prepend">
        <span id="success-message" style="color:red;"></span>
     </div>
    </div>
	<form id="add-user-form">
		@csrf 
	<div class="form-group input-group">
		<div class="input-group-prepend">
		    <span class="input-group-text"> <i class="fa fa-user"></i> </span>
		 </div>
        <input name="first_name" id="first_name" class="form-control" placeholder="First name" type="text">
    </div> 
    <div class="form-group input-group">
		<div class="input-group-prepend">
		    <span class="input-group-text"> <i class="fa fa-user"></i> </span>
		 </div>
        <input name="last_name" id="last_name" class="form-control" placeholder="Last name" type="text">
    </div> 
    <div class="form-group input-group">
    	<div class="input-group-prepend">
		    <span class="input-group-text"> <i class="fa fa-envelope"></i> </span>
		 </div>
        <input name="email" id="email" class="form-control" placeholder="Email address" type="email">
    </div> <!-- form-group// -->
    <div class="form-group input-group">
    	<div class="input-group-prepend">
		    <span class="input-group-text"> <i class="fa fa-phone"></i> </span>
		</div>
    	<input name="phone_number" id="phone_number" class="form-control" placeholder="Phone number" type="text">
    </div> <!-- form-group// -->
    <div class="form-group input-group">
    	<div class="input-group-prepend">
		    <span class="input-group-text"> <i class="fa fa-building"></i> </span>
		</div>
		<select class="form-control" name="user_type" id="user_type">
			<option selected=""> Select type</option>
			<option value="1">Advertiser</option>
			<option value="2">Publisher</option>
			<option value="3">Both</option>
		</select>
	</div>
  <div class="form-group input-group">
      <div class="input-group-prepend">
        <span class="input-group-text"> <i class="fa fa-building"></i> </span>
    </div>
    <select class="form-control" name="cat_id" id="cat_id">
         <option value=""> Select Website Category </option>
          @foreach($cat_name_List as $row)
          <option value="{{$row->id}}">{{$row->cat_name}}</option>
          @endforeach
    </select>
  </div>

    <div class="form-group input-group">
    	<div class="input-group-prepend">
		    <span class="input-group-text"> <i class="fa fa-lock"></i> </span>
		</div>
        <input class="form-control" placeholder="Create password" type="password" name="password" id="password">
    </div> <!-- form-group// -->
    <div class="form-group input-group">
    	<div class="input-group-prepend">
		    <span class="input-group-text"> <i class="fa fa-lock"></i> </span>
		</div>
        <input class="form-control" placeholder="Repeat password" type="password" name="repeat_password" id="repeat_password">
    </div>                                   
    <div class="form-group">
        <button type="submit" id="start-task-btn" class="btn btn-primary btn-block"> Create Account  </button>
    </div>                                                                  
</form>
</article>
</div>
</div>
<!-- <script src="//maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script> -->
<link href="toastr.css" rel="stylesheet">
<script src="//code.jquery.com/jquery.min.js"></script>
<script src="toastr.js"></script>

<script type="text/javascript">
  
function showInvalidFields(obj) {
  $.each(obj, function (key, value) {
    $(`#${key},.${key}`).addClass('is-invalid');
    $(`#${key}-error`).remove();
    let errEle = `<span id="${key}-error" class="error invalid-feedback">${value[0]}</span>`;
    $(`#${key}`).closest('.form-group').append(errEle);

    $(`#${key},.${key}`).on('focus change', function () {
      $(`#${key},.${key}`).removeClass('is-invalid');
      $(`#${key}-error`).remove();
    });
  });
}
  
//   let isAjax = false;
// // Add Latest News data
//     $("#add-user-form").on("submit", function(e) {
//       e.preventDefault();
//       $.ajax({
//         url: "{{url('')}}/ajax/registration/add",
//         type: 'post',
//         data: new FormData(this),
//         contentType: false,
//         processData: false,
//          success: function(res) {
//           if (res.code == 200) {
//               $('#success-message').text(res.msg);
//               $("#add-user-form")[0].reset();
//           } else if (res.code == 100) {
//             showInvalidFields(res.err);
//           } else {
//               $('#success-message').text(res.msg);
//           }
//         }
//       });
//     });






     let isAjax = false;
    $('#add-user-form').on('submit', function(e) {

      e.preventDefault();
      let formEle = "#login-form";

      if (!isAjax) {
        isAjax = true;
        $('#start-task-btn').attr('disabled', 'true');
        let btnText = $('#start-task-btn').html();
        //activeLoadingBtn("#start-task-btn");

        // let Toast = Swal.mixin({
        //   toast: true,
        //   position: 'center',
        //   showConfirmButton: false,
        //   timer: 3000
        // });

        $.ajax({
          type: 'POST',
          url: `{{url('')}}/ajax/registration/add`,
          data: new FormData(this),
          contentType: false,
          processData: false,
          success: function(res) {

            if (res.code === 200) {
                     $('#success-message').text(res.message);
                     $("#add-user-form")[0].reset();
              // Toast.fire({
              //   icon: 'success',
              //   title: res.msg
              // });

              // $(formEle).trigger("reset");

              setTimeout(function() {
                window.open(res.url, '_self');
              }, 100);

            } else if (res.code === 100) {
              showInvalidFields(res.err);
        if(res.err.work_from)
              {
        var swomsg = res.err.work_from;
          $('#user_types').html(swomsg);
              }
            } else if (res.code === 101) {
              // Toast.fire({
              //   icon: 'error',
              //   title: res.msg
              // });

            } else {
              $('#success-message').text(res.message);
              Toast.fire({
                icon: 'error',
                title: res.msg
              });
             }

            isAjax = false;
            $('#start-task-btn').removeAttr('disabled');
            //resetLoadingBtn("#start-task-btn", btnText);
          },
          error: function(xhr, status, err) {
            ajaxErrorCalback(xhr, status, err)
            isAjax = false;
            $('#start-task-btn').removeAttr('disabled');
            //resetLoadingBtn("#start-task-btn", btnText);
          }
        });
      }

    });


</script> 