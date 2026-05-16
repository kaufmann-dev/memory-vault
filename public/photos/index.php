<?php
    session_start();
?>
<!DOCTYPE html>
<html lang="en">
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
    <body>
        <?php
            $users = json_decode(file_get_contents('../../json/users.json'), true)['users'];
            $selfies = array_diff(scandir("../../img/face/full"), array('.', '..'));
            $bodyphotos = array_diff(scandir("../../img/body/full"), array('.', '..'));
            $nav = file_get_contents('../nav');

            $start = new DateTime();
            $start->modify("-2 months");
            $end = new DateTime();

            function Authenticate(){
                global $users;
                foreach($users as $lola){
                    if(isset($_SESSION["username"]) && $_SESSION["username"] == $lola['username'] && $_SESSION["password"] == $lola['password']){
                        return true;
                    }
                }
                return false;
            }

            eval("?> $nav <?php ");

            if(Authenticate() && isset($_FILES["cropped_face"]) && isset($_FILES["full_face"]) && isset($_POST["face_date"])){
                $target_closeup = "../../img/face/closeup/" . DateTime::createFromFormat('Y-m-d', $_POST["face_date"])->format('Ymd') . ".webp";
                $target_full = "../../img/face/full/" . DateTime::createFromFormat('Y-m-d', $_POST["face_date"])->format('Ymd') . ".webp";

                if ($_FILES["cropped_face"]["size"] > 4000000 || $_FILES["full_face"]["size"] > 4000000) {
                    exit('<div class="container my-3">Error. Maximum file size is 4 MB.</div></body></html>');
                }

                if (file_exists($target_full) || file_exists($target_closeup)) {
                    exit('<div class="container my-3">Error. File already exists.</div></body></html>');
                }

                if(!in_array(strtolower(pathinfo($_FILES["cropped_face"]["name"],PATHINFO_EXTENSION)), array("webp", "jpg", "jpeg", "gif", "png")) || !in_array(strtolower(pathinfo($_FILES["full_face"]["name"],PATHINFO_EXTENSION)), array("webp", "jpg", "jpeg", "gif", "png"))) {
                    exit('<div class="container my-3">Error. Please only use webp, jpg, jpeg, gif or png format.</div></body></html>');
                }

                if (!move_uploaded_file($_FILES["full_face"]["tmp_name"], $target_full) || !move_uploaded_file($_FILES["cropped_face"]["tmp_name"], $target_closeup)) {
                    exit('<div class="container my-3">Error. Unknown.</div></body></html>');
                }
            }

            if(Authenticate() && isset($_FILES["hairline_body"]) && isset($_FILES["full_body"]) && isset($_POST["body_date"])){
                $target_hairline = "../../img/body/hairline/" . DateTime::createFromFormat('Y-m-d', $_POST["body_date"])->format('Ymd') . ".webp";
                $target_full = "../../img/body/full/" . DateTime::createFromFormat('Y-m-d', $_POST["body_date"])->format('Ymd') . ".webp";

                if ($_FILES["hairline_body"]["size"] > 4000000 || $_FILES["full_body"]["size"] > 4000000) {
                    exit('<div class="container my-3">Error. Maximum file size is 4 MB.</div></body></html>');
                }

                if (file_exists($target_full) || file_exists($target_hairline)) {
                    exit('<div class="container my-3">Error. File already exists.</div></body></html>');
                }

                if(!in_array(strtolower(pathinfo($_FILES["full_body"]["name"],PATHINFO_EXTENSION)), array("webp", "jpg", "jpeg", "gif", "png")) || !in_array(strtolower(pathinfo($_FILES["hairline_body"]["name"],PATHINFO_EXTENSION)), array("webp", "jpg", "jpeg", "gif", "png"))) {
                    exit('<div class="container my-3">Error. Please only use webp, jpg, jpeg, gif or png format.</div></body></html>');
                }

                if (!move_uploaded_file($_FILES["full_body"]["tmp_name"], $target_full) || !move_uploaded_file($_FILES["hairline_body"]["tmp_name"], $target_hairline)) {
                    exit('<div class="container my-3">Error. Unknown.</div></body></html>');
                }
            }

            function UpdateFaceList(){
                global $selfies;
                global $start;
                global $end;

                if(!empty($_POST["start"])){
                    $start = DateTime::createFromFormat('Y-m-d', $_POST["start"]);
                }
                if(!empty($_POST["end"])){
                    $end = DateTime::createFromFormat('Y-m-d', $_POST["end"]);
                }

                $ord = array();
                foreach ($selfies as $key => $value){
                    $ord[] = strtotime(substr($value, 0, 8));
                }
                array_multisort($ord, SORT_DESC, $selfies);

                foreach($selfies as $selfie){
                    $selfiedate = DateTime::createFromFormat('Ymd', substr($selfie, 0, 8));
                    if($selfiedate >= $start && $selfiedate <= $end){
                        $date = date_diff(new DateTime(), DateTime::createFromFormat('Ymd', substr($selfie, 0, 8)))->format("%a") . " days ago (" . DateTime::createFromFormat('Ymd', substr($selfie, 0, 8))->format('j.n.Y') . ")";
                        
                        $src1 = 'data: '.mime_content_type("../../img/face/full/".$selfie).';base64,'.base64_encode(file_get_contents("../../img/face/full/".$selfie));
                        $src2 = 'data: '.mime_content_type("../../img/face/closeup/".$selfie).';base64,'.base64_encode(file_get_contents("../../img/face/closeup/".$selfie));
                        ?>
                            <div class="col-12 col-sm-6 col-lg-4 mb-3">
                                <div class="card h-100 overflow-hidden">
                                    <div class="row row-cols-12 m-0 h-100">
                                        <div class="col d-flex align-items-center p-2 borderendcard">
                                            <img src="<?php echo $src1; ?>" class="w-100 rounded shadow" alt="full face picture from <?php echo $date; ?>">
                                        </div>
                                        <div class="col d-flex align-items-center p-2 w-100">
                                            <img src="<?php echo $src2; ?>" class="w-100 rounded shadow" alt="closeup face picture from <?php echo $date; ?>">
                                        </div>
                                    </div>
                                    <div class="card-body p-0"></div>
                                    <div class="card-footer text-black-50 text-center"><?php echo $date; ?></div>
                                </div>
                            </div>
                        <?php
                    }
                }
            }

            function UpdateBodyList(){
                global $bodyphotos;
                global $start;
                global $end;

                if(!empty($_POST["start"])){
                    $start = DateTime::createFromFormat('Y-m-d', $_POST["start"]);
                }
                if(!empty($_POST["end"])){
                    $end = DateTime::createFromFormat('Y-m-d', $_POST["end"]);
                }

                $ord = array();
                foreach ($bodyphotos as $key => $value){
                    $ord[] = strtotime(substr($value, 0, 8));
                }
                array_multisort($ord, SORT_DESC, $selfies);

                foreach($bodyphotos as $selfie){
                    $selfiedate = DateTime::createFromFormat('Ymd', substr($selfie, 0, 8));
                    if($selfiedate >= $start && $selfiedate <= $end){
                        $date = date_diff(new DateTime(), DateTime::createFromFormat('Ymd', substr($selfie, 0, 8)))->format("%a") . " days ago (" . DateTime::createFromFormat('Ymd', substr($selfie, 0, 8))->format('j.n.Y') . ")";
                        
                        $src1 = 'data: '.mime_content_type("../../img/body/full/".$selfie).';base64,'.base64_encode(file_get_contents("../../img/body/full/".$selfie));
                        $src2 = 'data: '.mime_content_type("../../img/body/hairline/".$selfie).';base64,'.base64_encode(file_get_contents("../../img/body/hairline/".$selfie));
                        ?>
                            <div class="col-12 col-sm-6 col-lg-4 mb-3">
                                <div class="card h-100 overflow-hidden">
                                    <div class="row row-cols-12 m-0 h-100">
                                        <div class="col d-flex align-items-center p-2 borderendcard">
                                            <img src="<?php echo $src1; ?>" class="w-100 rounded shadow" alt="full body picture from <?php echo $date; ?>">
                                        </div>
                                        <div class="col d-flex align-items-center p-2 w-100">
                                            <img src="<?php echo $src2; ?>" class="w-100 rounded shadow" alt="hairline picture from <?php echo $date; ?>">
                                        </div>
                                    </div>
                                    <div class="card-body p-0"></div>
                                    <div class="card-footer text-black-50 text-center"><?php echo $date; ?></div>
                                </div>
                            </div>
                        <?php
                    }
                }
            }
            
        ?>
        <div class="container mt-3">
            <?php
                if(!Authenticate()){
                    echo"You need to be logged in to access this page.";
                } else { ?>
                    <nav class="mb-3">
                        <div class="nav nav-pills" id="nav-tab" role="tablist">
                            <button class="nav-link" id="nav-face-tab" data-bs-toggle="tab" data-bs-target="#nav-face" type="button" role="tab" aria-controls="nav-face" aria-selected="true">Photos: Face</button>
                            <button class="nav-link" id="nav-body-tab" data-bs-toggle="tab" data-bs-target="#nav-body" type="button" role="tab" aria-controls="nav-body" aria-selected="true">Photos: Body</button>
                            <button class="nav-link" id="nav-range-tab" data-bs-toggle="tab" data-bs-target="#nav-range" type="button" role="tab" aria-controls="nav-range" aria-selected="false">Select range</button>
                            <button class="nav-link" id="nav-newface-tab" data-bs-toggle="tab" data-bs-target="#nav-newface" type="button" role="tab" aria-controls="nav-newface" aria-selected="false"><i class="bi bi-plus-circle"></i> New: Face</button>
                            <button class="nav-link" id="nav-newbody-tab" data-bs-toggle="tab" data-bs-target="#nav-newbody" type="button" role="tab" aria-controls="nav-newbody" aria-selected="false"><i class="bi bi-plus-circle"></i> New: Body</button>
                        </div>
                    </nav>
                    <div class="tab-content" id="nav-tabContent">
                        <div class="tab-pane fade" id="nav-face" role="tabpanel" aria-labelledby="nav-face-tab">
                            <h1 class="display-4">Photos: Face</h1>
                            <p class="text-black-50">Recognise the changes in the look of your face</p>
                            <div class="row"><?php UpdateFaceList(); ?></div>
                        </div>
                        <div class="tab-pane fade" id="nav-body" role="tabpanel" aria-labelledby="nav-body-tab">
                            <h1 class="display-4">Photos: Body</h1>
                            <p class="text-black-50">Recognise the changes in the look of your body</p>
                            <div class="row"><?php UpdateBodyList(); ?></div>
                        </div>
                        <div class="tab-pane fade" id="nav-range" role="tabpanel" aria-labelledby="nav-range-tab">
                            <form class="row" method='POST'>
                                <div class="col-6 col-sm-12 col-md-6 mb-2">
                                    <label class="form-label mb-1">Start</label>
                                    <input required class="form-control form-control-sm" id="start" type="date" name="start" value=<?php echo $start->format('Y-m-d'); ?>>
                                </div>
                                <div class="col-6 col-sm-12 col-md-6 mb-2">
                                    <label class="form-label mb-1">End</label>
                                    <input required class="form-control form-control-sm" id="end" type="date" name="end" value=<?php echo $end->format('Y-m-d'); ?>>
                                </div>
                                <div class="col-12 d-flex align-items-end mb-2">
                                    <button type="submit" class="btn btn-primary btn-sm w-100">Display</button>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="nav-newface" role="tabpanel" aria-labelledby="nav-newface-tab">
                            <form class="row" method='POST' enctype="multipart/form-data">
                                <div class="col-12 col-lg-6 mb-2">
                                    <label class="form-label mb-1">Full face</label>
                                    <input required name="full_face" class="form-control form-control-sm" type="file">
                                </div>
                                <div class="col-12 col-lg-6 mb-2">
                                    <label class="form-label mb-1">Cropped face</label>
                                    <input required name="cropped_face" class="form-control form-control-sm" type="file">
                                </div>
                                <div class="col-6 col-sm-8 col-lg-6 mb-2">
                                    <label class="form-label mb-1">Date</label>
                                    <input required name="face_date" value="<?php echo(new DateTime())->format('Y-m-d'); ?>" class="form-control form-control-sm" type="date">
                                </div>
                                <div class="col-6 col-sm-4 col-lg-6 d-flex align-items-end mb-2">
                                    <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-cloud-arrow-up"></i> Add</button>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="nav-newbody" role="tabpanel" aria-labelledby="nav-newbody-tab">
                            <form class="row" method='POST' enctype="multipart/form-data">
                                <div class="col-12 col-lg-6 mb-2">
                                    <label class="form-label mb-1">Full body</label>
                                    <input required name="full_body" class="form-control form-control-sm" type="file">
                                </div>
                                <div class="col-12 col-lg-6 mb-2">
                                    <label class="form-label mb-1">Hairline</label>
                                    <input required name="hairline_body" class="form-control form-control-sm" type="file">
                                </div>
                                <div class="col-6 col-sm-8 col-lg-6 mb-2">
                                    <label class="form-label mb-1">Date</label>
                                    <input required name="body_date" value="<?php echo(new DateTime())->format('Y-m-d'); ?>" class="form-control form-control-sm" type="date">
                                </div>
                                <div class="col-6 col-sm-4 col-lg-6 d-flex align-items-end mb-2">
                                    <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-cloud-arrow-up"></i> Add</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php } ?>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>

        <script>
            $(document).ready(function(){
                if(localStorage.getItem('activeTabFace') === null || localStorage.getItem('activeTabFace') === "undefined"){
                    localStorage.setItem('activeTabFace', $('div#nav-tab button').first().data('bs-target'));
                }
                $('div#nav-tab button').click(function(){
                    localStorage.setItem('activeTabFace', $(this).data('bs-target'));
                });
                $('#nav-tabContent ' + localStorage.getItem('activeTabFace')).addClass('show active');
                $('#nav-tab ' + localStorage.getItem('activeTabFace') + '-tab').addClass('active');
            });
        </script>
    </body>
</html>
