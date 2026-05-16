<?php
    session_start();
?>
<!DOCTYPE html>
<html class="h-100" lang="en">
    <head>
        <title>The Second Directory</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="The Second Directory.">
        <link href="/css/style.css" rel="stylesheet">
        <link href="/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="/css/bootstrap-icons.min.css">
        <link rel="icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/favicon.ico">
    </head>
    <body class="h-100">
        <?php
            $users = json_decode(file_get_contents('../../json/users.json'), true)['users'];
            $nav = file_get_contents('../nav');

            function Authenticate(){
                global $users;
                foreach($users as $lola){
                    if(isset($_SESSION["username"]) && $_SESSION["username"] == $lola['username'] && $_SESSION["password"] == $lola['password']){
                        return true;
                    }
                }
                return false;
            }

        ?>
            <div class="h-100 d-flex flex-column">
                <div class="flex-soy">
                    <?php eval("?> $nav <?php "); ?>
                </div>
                <div class="flex-soy border-bottom py-3">
                    <div class="container">
                        <h1 class="display-4">Heartrate Calculator</h1>
                        <p class="text-black-50">Calculate your heartrate</p>
                    </div>
                </div>
                <div class="d-flex flex-column flex-chad">
                    <div id="pe" class="container text-center flex-chad position-relative mh220">
                        <button id="gl" class="btn position-absolute top-50 start-50 translate-middle btn-primary rounded-circle bi bi-activity"></button>
                    </div>
                    <div id="output" class="text-center flex-soy">
                        <div class='border-top pt-3 pb-3 container-fluid'><span class='azdeg mb-3 d-inline-block'>0 BPM</span><br><button id='reset' class='btn btn-dark btn-lg'>Stop</button></div>
                    </div>
                </div>
            </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
        <script src="/js/jquery.resize.min.js"></script>
        <script>
            document.addEventListener("DOMContentLoaded", function(){
                var count = 0;
                var seconds = 0;
                var timer = setInterval.bind(null, function () {
                    seconds++;
                    document.querySelector("#output span").textContent=Math.trunc(count*60/seconds*10)+" BPM";
                }, 100);
                var bratan;
                $("#output").hide();
                $('#gl').click(function(){
                    if (seconds == 0) {
                        count++;
                        bratan = timer();
                        setTimeout(function(){
                            $("#output").show();
                        },100);
                    }
                    else {
                        count++;
                    }
                });
                $('#reset').click(function(){
                    clearInterval(bratan);
                    count = 0;
                    seconds = 0;
                });
                
                function lol(target_element = document.querySelector('#gl'), source_element = document.querySelector('#pe'), percents = 50) {
                    let h = Math.min(source_element.getBoundingClientRect().width, source_element.getBoundingClientRect().height);
                    target_element.style.width = (h * percents / 100) + 'px';
                    target_element.style.height = (h * percents / 100) + 'px';
                    target_element.style.fontSize  = (h * percents / 200) + 'px';
                }
                $("#pe").resize(function(){
                    lol();
                });
                lol();
            });
        </script>
    </body>
</html>
