<style>
    @import url('https://fonts.googleapis.com/css?family=Montserrat&display=swap');

*{
	margin: 0;
	padding: 0;
	box-sizing: border-box;
	font-family: 'Montserrat', sans-serif;
}

body{
	background: #ececec;
}

.wrapper{

}

.notification_wrap{
	width: 500px;
	margin: 120px auto 0;
}

.notification_wrap .notification_icon{
	position: relative;
	width: 50px;
	height: 50px;
	font-size: 32px;
	margin: 0 auto;
	text-align: center;
	color: #605dff;
}

.notification_wrap .notification_icon .fa-bell{
	cursor: pointer;
}

.notification_wrap .dropdown{
	width: 350px;
	height: auto;
	background: #fff;
	border-radius: 5px;
	box-shadow: 2px 2px 3px rgba(0,0,0,0.125);
	margin: 15px auto 0;
	padding: 15px;
	position: relative;
	display: none;
}

.notification_wrap .dropdown .notify_item{
	display: flex;
	align-items: center;
	padding: 10px 0;
	border-bottom: 1px solid #dbdaff;
}

.notification_wrap .dropdown .notify_item:last-child{
	border-bottom: 0px;
}

.notification_wrap .dropdown .notify_item .notify_img{
	margin-right: 15px;
}

.notification_wrap .dropdown .notify_item .notify_info p{
	margin-bottom: 5px;
}

.notification_wrap .dropdown .notify_item .notify_info p span{
	color: #605dff;
	margin-left: 5px;
}

.notification_wrap .dropdown .notify_item .notify_info .notify_time{
	color: #c5c5e6;
	font-size: 12px;
}

.notification_wrap .dropdown:before{
	content: "";
	position: absolute;
	top: -30px;
	left: 50%;
	transform: translateX(-50%);
	border: 15px solid;
	border-color: transparent transparent #fff transparent;
}

.notification_wrap .dropdown.active{
	display: block;
}
</style>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Notification Dropdown</title>
	<link rel="stylesheet" href="styles.css">
	<script src="https://kit.fontawesome.com/b99e675b6e.js"></script>
	<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
	<script>
		$(document).ready(function(){
			$(".notification_icon .fa-bell").click(function(){
				$(".dropdown").toggleClass("active");
			})
		});
	</script>
</head>
<body>

<div class="wrapper">
	<div class="notification_wrap">
		<div class="notification_icon">
			<i class="fas fa-bell"></i>
		</div>
		<div class="dropdown">
			<div class="notify_item">
				<div class="notify_img">
					<img src="images/not_1.png" alt="profile_pic" style="width: 50px">
				</div>
				<div class="notify_info">
					<p>Alex commented on<span>Timeline Share</span></p>
					<span class="notify_time">10 minutes ago</span>
				</div>
			</div>
			<div class="notify_item">
				<div class="notify_img">
					<img src="images/not_2.png" alt="profile_pic" style="width: 50px">
				</div>
				<div class="notify_info">
					<p>Ben hur commented on your<span>Timeline Share</span></p>
					<span class="notify_time">55 minutes ago</span>
				</div>
			</div>
			<div class="notify_item">
				<div class="notify_img">
					<img src="images/not_3.png" alt="profile_pic" style="width: 50px">
				</div>
				<div class="notify_info">
					<p>Meryn trant liked your<span>Cover Picture</span></p>
					<span class="notify_time">2 hours ago</span>
				</div>
			</div>
			<div class="notify_item">
				<div class="notify_img">
					<img src="images/not_4.png" alt="profile_pic" style="width: 50px">
				</div>
				<div class="notify_info">
					<p>John wick commented on your<span>Profile Picture</span></p>
					<span class="notify_time">6 hours ago</span>
				</div>
			</div>
		</div>
	</div>
</div>

</body>
</html>
