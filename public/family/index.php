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
        <link rel="stylesheet" type="text/css" href="/css/datatables.min.css"/>
        <link href="./treestyle.css" rel="stylesheet">
        <link href="/css/style.css" rel="stylesheet">
        <link rel="icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/favicon.ico">
    </head>
    <body class="h-100">
        <?php
            $users = json_decode(file_get_contents('../../json/users.json'), true)['users'];
            $david = json_decode(file_get_contents('../../json/family.json'), true)['david'];
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

            function PrintFamily($var){
                echo PHP_EOL."<li>".PHP_EOL;
                if($var["sex"] == "male"){
                    echo'<div class="person_wrapper male rounded-top overflow-auto" style="width:200px;">';
                        if(!empty($var["portrait"])) {
                            echo'<div class="portrait mb"><img alt="portrait" src="'.'data: '.mime_content_type("../../img/ancestors/".$var["portrait"]).';base64,'.base64_encode(file_get_contents("../../img/ancestors/".$var["portrait"])).'" class="w-100"></div>';
                            echo'<div class="rounded-bottom mb nbt">';
                        } else {
                            echo'<div class="rounded mb">';
                        }
                            echo'<div class="p-1">'.$var["name"].'</div>';
                            if(!empty($var["initial_name"])) { echo'<div class="mbt p-1 text-black-50">'.$var["initial_name"].'</div>'; }
                            if(!empty($var["profession"])) { echo'<div class="mbt p-1">💼 '.$var["profession"].'</div>'; }
                            if(!empty($var["military_rank"])) { echo'<div class="mbt p-1">✠ '.$var["military_rank"].'</div>'; }
                            if(!empty($var["birth"])) { echo'<div class="mbt p-1">ᛉ '.DateTime::createFromFormat('Y-m-d', $var["birth"])->format('d/m/y').'</div>'; }
                            if(!empty($var["death"])) { echo'<div class="mbt p-1">ᛦ '.DateTime::createFromFormat('Y-m-d', $var["death"])->format('d/m/y').'</div>'; }
                        echo'</div>';
                    echo'</div>';
                } else {
                    echo'<div class="person_wrapper female rounded-top overflow-auto" style="width:200px;">';
                        if(!empty($var["portrait"])) {
                            echo'<div class="portrait fb"><img alt="portrait" src="'.'data: '.mime_content_type("../../img/ancestors/".$var["portrait"]).';base64,'.base64_encode(file_get_contents("../../img/ancestors/".$var["portrait"])).'" class="w-100"></div>';
                            echo'<div class="rounded-bottom fb nbt">';
                        } else {
                            echo'<div class="rounded fb">';
                        }
                            echo'<div class="p-1">'.$var["name"].'</div>';
                            if(!empty($var["initial_name"])) { echo'<div class="fbt p-1 text-black-50">'.$var["initial_name"].'</div>'; }
                            if(!empty($var["profession"])) { echo'<div class="fbt p-1">💼 '.$var["profession"].'</div>'; }
                            if(!empty($var["military_rank"])) { echo'<div class="fbt p-1">✠ '.$var["military_rank"].'</div>'; }
                            if(!empty($var["birth"])) { echo'<div class="fbt p-1">ᛉ '.DateTime::createFromFormat('Y-m-d', $var["birth"])->format('d/m/y').'</div>'; }
                            if(!empty($var["death"])) { echo'<div class="fbt p-1">ᛦ '.DateTime::createFromFormat('Y-m-d', $var["death"])->format('d/m/y').'</div>'; }
                        echo'</div>';
                    echo'</div>';
                }
                if(!empty($var["father"]) || !empty($var["mother"])){
                    echo PHP_EOL."<ul>".PHP_EOL;
                    if(!empty($var["father"])){ $waiter = PrintFamily($var["father"]); }
                    if(!empty($var["mother"])){ PrintFamily($var["mother"]); }
                    echo PHP_EOL."</ul>".PHP_EOL;
                }
                echo PHP_EOL."</li>".PHP_EOL;
            }
            
            eval("?> $nav <?php ");
        ?>
            <?php
                if(!Authenticate()){
                    echo"<div class='container my-3'>You need to be logged in to access this page.";
                } else {
                ?>
                
                <div class="container my-3" id="headingcon">
                    <h1 class="display-4">Family Tree</h1>
                    <p class="text-black-50">Never forget were you come from</p>
                </div>
                <div class="container-fluid mycalc hcalc">
                <div id="treewrapper" class="overflow-auto h-100 border rounded shadow">
                    <div class="tree">
                        <ul>
                            <?php PrintFamily($david); ?>
                        </ul>
                    </div>
                </div>
                <?php
                }
            ?>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
        
        <script src="/js/datatables.min.js"></script>

        <script>
            $(document).ready( function () {
                $('.hcalc').height("calc(100% - " + ($('#headingcon').outerHeight(true)+$('nav').outerHeight(true)) + "px - var(--bs-gutter-x) * .5)");
                const ele = document.querySelector('#treewrapper');
                ele.style.cursor = 'grab';
                let pos = { top: 0, left: 0, x: 0, y: 0 };
                const mouseDownHandler = function (e) {
                    ele.style.cursor = 'grabbing';
                    ele.style.userSelect = 'none';
                    pos = {
                        left: ele.scrollLeft,
                        top: ele.scrollTop,
                        x: e.clientX,
                        y: e.clientY,
                    };
                    document.addEventListener('mousemove', mouseMoveHandler);
                    document.addEventListener('mouseup', mouseUpHandler);
                };
                const mouseMoveHandler = function (e) {
                    const dx = e.clientX - pos.x;
                    const dy = e.clientY - pos.y;
                    ele.scrollTop = pos.top - dy;
                    ele.scrollLeft = pos.left - dx;
                };
                const mouseUpHandler = function () {
                    ele.style.cursor = 'grab';
                    ele.style.removeProperty('user-select');
                    document.removeEventListener('mousemove', mouseMoveHandler);
                    document.removeEventListener('mouseup', mouseUpHandler);
                };
                ele.addEventListener('mousedown', mouseDownHandler);

                let xd = $('.tree');
            } );
        </script>
    </body>
</html>