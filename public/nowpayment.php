<?php
$raw_post_data = file_get_contents('php://input');
file_put_contents('paypal.json',$raw_post_data);
