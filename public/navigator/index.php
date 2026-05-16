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
        <link href="/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="/css/bootstrap-icons.min.css">
        <link href="/css/style.css" rel="stylesheet">
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
                <form method="POST" class="d-flex flex-column flex-chad" action="javascript:void(0);" id="aziform">
                    <div class="flex-soy border-bottom py-3">
                        <div class="container">
                            <h1 class="display-4">Navigator</h1>
                            <p class="text-black-50">Calculate azimuth and distance from two sets of latitude and longitude</p>
                            <div class="rounded bg-dark p-1 my-3"></div>
                            <div class="row">
                                <div class="col-6">
                                    <label class="form-label">Target latitude</label>
                                    <input type="number" step="any" id="targetLatitude" class="form-control" required min="-90" max="90">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Target longitude</label>
                                    <input type="number" step="any" id="targetLongitude" class="form-control" required min="-180" max="180">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="pe" class="container text-center flex-chad position-relative mh220">
                        <button id="gl" type="submit" class="btn position-absolute top-50 start-50 translate-middle btn-primary rounded-circle"><i class="bi bi-compass-fill"></i></button>
                    </div>
                    <div id="output" class="text-center flex-soy"></div>
                </form>
            </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
        <script src="/js/jquery.resize.min.js"></script>
        <script>
            $(document).ready( function () {
                let options = {
                    enableHighAccuracy: true,
                    timeout: 5000,
                    maximumAge: 0
                };

                var lat, long;

                function success(pos) {
                    let target = {
                        latitude : lat,
                        longitude : long
                    };
                    let crd = pos.coords;
                    sinlondif = Math.sin(target.longitude*Math.PI/180-crd.longitude*Math.PI/180);
                    coslondif = Math.cos(target.longitude*Math.PI/180-crd.longitude*Math.PI/180);
                    let x = Math.sin(target.longitude*Math.PI/180-crd.longitude*Math.PI/180) * Math.cos(target.latitude*Math.PI/180);
                    let y = Math.cos(crd.latitude*Math.PI/180) * Math.sin(target.latitude*Math.PI/180) - Math.sin(crd.latitude*Math.PI/180) * Math.cos(target.latitude*Math.PI/180) * Math.cos(target.longitude*Math.PI/180-crd.longitude*Math.PI/180);
                    let azdeg = Math.ceil(Math.atan2(x,y)*(180/ Math.PI));
                    if(azdeg<0){azdeg+=360};
                    let a = Math.pow(Math.sin((target.latitude*Math.PI/180-crd.latitude*Math.PI/180)/2), 2)+Math.cos(crd.latitude*Math.PI/180)*Math.cos(target.latitude*Math.PI/180)*Math.pow(Math.sin((target.longitude*Math.PI/180-crd.longitude*Math.PI/180)/2),2);
                    let ms = Math.round(2*6371000*Math.atan2(Math.sqrt(a), Math.sqrt(1-a)));
                    let kms = Math.round(2*6371000*Math.atan2(Math.sqrt(a), Math.sqrt(1-a))/1000);
                    $("#output").html("<div class='border-top pt-2 pb-3 container-fluid'><span class='azdeg'>"+azdeg+"°</span><br>"+Number(crd.latitude).toFixed(6)+" <span class='text-black-50'>(you)</span> "+Number(crd.longitude).toFixed(6)+"</span><br>"+Number(target.latitude).toFixed(6)+" <span class='text-black-50'>(target)</span> "+Number(target.longitude).toFixed(6)+"<br>"+kms+" <span class='text-black-50'>(km | m)</span> "+ms+"</div>");
                }

                $('#aziform').on("submit", function() {
                    lat = $('#targetLatitude').val();
                    long = $('#targetLongitude').val();
                    navigator.geolocation.getCurrentPosition(success, undefined, options);
                });
                
                function lol(target_element = document.querySelector('#pe button'), source_element = document.querySelector('#pe'), percents = 50) {
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
