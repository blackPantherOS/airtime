<?php
?>

<form action="#" role="form" id="rmqSettingsForm">
    <h3 class="form-title">RabbitMQ Settings</h3>
    <span id="helpBlock" class="help-block help-message"></span>
    <p>
        RabbitMQ is an AMQP-based messaging system used by Airtime. You should only edit these settings
        if you have changed the defaults since running the installer, or if you've opted to install RabbitMQ manually.
    </p>
    <p>
        In either case, we recommend that you change at least the default password provided -
        you can do this by running the following line from the command line:<br/>
        <code>sudo rabbitmqctl change_password &lt;username&gt; &lt;newpassword&gt;</code>
    </p>
    <div id="rmqSlideToggle">
        <span><strong>Advanced </strong></span><span id="advCaret" class="caret"></span><hr/>
    </div>
    <div id="rmqFormBody">
        <div class="form-group">
            <label class="control-label" for="rmqUser">Username</label>
            <input required class="form-control" type="text" name="rmqUser" id="rmqUser" placeholder="Username" value="airtime"/>
            <span class="glyphicon glyphicon-remove form-control-feedback"></span>
        </div>
        <div class="form-group">
            <label class="control-label" for="rmqPass">Password</label>
            <input class="form-control" type="password" name="rmqPass" id="rmqPass" placeholder="Password" value="airtime"/>
            <span class="glyphicon glyphicon-remove form-control-feedback"></span>
            <span id="rmqHelpBlock" class="help-block">
                You probably want to change this!
            </span>
        </div>
        <div class="form-group">
            <label class="control-label" for="rmqHost">Host</label>
            <input required class="form-control" type="text" name="rmqHost" id="rmqHost" placeholder="Host" value="127.0.0.1"/>
        </div>
        <div class="form-group">
            <label class="control-label" for="rmqPort">Port</label>
            <input required class="form-control" type="text" name="rmqPort" id="rmqPort" placeholder="Port" value="5672"/>
        </div>
        <div class="form-group">
            <label class="control-label" for="rmqVHost">Virtual Host</label>
            <input required class="form-control" type="text" name="rmqVHost" id="rmqVHost" placeholder="VHost" value="/airtime"/>
            <span class="glyphicon glyphicon-remove form-control-feedback"></span>
        </div>
        <input class="form-control" type="hidden" name="rmqErr" id="rmqErr" aria-describedby="helpBlock"/>
    </div>
    <div>
        <input type="submit" formtarget="rmqSettingsForm" class="btn btn-primary btn-next" value="Next &#10097;"/>
        <input type="button" class="btn btn-primary btn-back" value="&#10096; Back"/>
        <input type="button" class="btn btn-default btn-skip" value="Skip this step &#10097;"/>
    </div>
</form>

<script>
    $("#rmqSlideToggle").click(function() {
        $("#rmqFormBody").slideToggle(500);
        $("#advCaret").toggleClass("caret-up");
    });

    $("#rmqSettingsForm").submit(function(e) {
        resetFeedback();
        e.preventDefault();
        var d = $('#rmqSettingsForm').serializeArray();
        addOverlay();
        // Append .promise().done() rather than using a
        // callback to avoid weird alert duplication
        $("#overlay, #loadingImage").fadeIn(500).promise().done(function() {
            // Proxy function for passing the event to the cleanup function
            var cleanupProxy = function(data) {
                cleanupStep.call(this, data, e);
            };
            $.post('setup/setup-functions.php?obj=RabbitMQSetup', d, cleanupProxy, "json");
        });
    });
</script>
