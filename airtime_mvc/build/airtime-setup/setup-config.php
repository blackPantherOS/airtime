<?php

?>

<html style="background-color:#111141;">
    <head>
        <link rel="stylesheet" type="text/css" href="css/bootstrap-3.3.1.min.css">
    </head>
    <body style="background-color:#111141;color:white;padding: 2em 0; min-width: 400px; width: 30%; text-align: center; margin: 3em auto;">
        <img src="css/images/airtime_logo_jp.png" style="margin-bottom: .5em;" /><br/>
        <form role="form" style="margin-top: 2em;">
            <h2>Database Settings</h2>
            <div class="form-group col-xs-6">
                <label class="sr-only" for="dbUser">Database Username</label>
                <input class="form-control" type="text"  id="dbUser" placeholder="Username"/>
            </div>
            <div class="form-group col-xs-6">
                <label class="sr-only" for="dbPass">Database Password</label>
                <input class="form-control" type="password" id="dbPass" placeholder="Password"/>
            </div>
            <div class="form-group col-xs-6">
                <label class="sr-only" for="dbName">Database Name</label>
                <input class="form-control" type="text" id="dbName" placeholder="Name"/>
            </div>
            <div class="form-group col-xs-6">
                <label class="sr-only" for="dbHost">Database Host</label>
                <input class="form-control" type="text" id="dbHost" placeholder="Host" value="localhost"/>
            </div>
            <input type="submit" class="btn btn-default"/>
        </form>