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
            $diary = json_decode(file_get_contents('../../json/diary.json'), true)['entries'];
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

            $wordcount = 0;
            for ($i = 0; $i < count($diary); $i++) {
                $wordcount += $diary[$i]['words'];
            }

            if(Authenticate()){
                if(!empty($_POST["content"]) && !empty($_POST["words"]) && !empty($_POST["lang"])) {
                    $entry["id"] = null;
                    foreach($diary as $bruh){
                        if($bruh["id"] > $entry["id"]){
                            $entry["id"] = $bruh["id"];
                        }
                    }
                    $entry["id"]++;

                    $entry["lang"] = $_POST["lang"];
                    $entry["words"] = intval($_POST["words"]);

                    $entry["date"] = (new DateTime())->format('Y-m-d H:i');
                    if(!empty($_POST["title"])){
                        $entry["title"] = $_POST["title"];
                    } else {
                        $entry["title"] = null;
                    }
                    if(!empty($_POST["description"])){
                        $entry["description"] = str_replace("\r\n", "\n", $_POST["description"]);
                    } else {
                        $entry["description"] = null;
                    }
                    $entry["content"] = str_replace("\r\n", "\n", $_POST["content"]);

                    array_push($diary, $entry);
                    $gringo = array('entries'=>$diary);
                    file_put_contents('../../json/diary.json', json_encode($gringo, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["to_delete"])){
                    foreach ($diary as $key => $field) {
                        if ($field['id'] == intval($_POST["to_delete"])) {
                            unset($diary[$key]);
                        }
                    }
                    $gringo = array('entries'=>$diary);
                    file_put_contents('../../json/diary.json', json_encode($gringo, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(!empty($_POST["update_content"]) && !empty($_POST["update_words"]) && !empty($_POST["update_lang"]) && !empty($_POST["to_update"])) {
                    foreach ($diary as $key => $field) {
                        if ($field['id'] == intval($_POST["to_update"])) {
                            $diary[$key]["lang"] = $_POST["update_lang"];
                            $diary[$key]["words"] = intval($_POST["update_words"]);

                            if(!empty($_POST["update_title"])){
                                $diary[$key]["title"] = $_POST["update_title"];
                            } else {
                                $diary[$key]["title"] = null;
                            }
                            if(!empty($_POST["update_description"])){
                                $diary[$key]["description"] = str_replace("\r\n", "\n", $_POST["update_description"]);
                            } else {
                                $diary[$key]["description"] = null;
                            }
                            $diary[$key]["content"] = str_replace("\r\n", "\n", $_POST["update_content"]);
                        }
                    }
                    $gringo = array('entries'=>$diary);
                    file_put_contents('../../json/diary.json', json_encode($gringo, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
            }

            usort($diary, function($a, $b) {
                $dateTimestamp1 = DateTime::createFromFormat('Y-m-d H:i', $a["date"])->getTimestamp();
                $dateTimestamp2 = DateTime::createFromFormat('Y-m-d H:i', $b["date"])->getTimestamp();
                
                return $dateTimestamp1 > $dateTimestamp2 ? -1: 1;
            });
            
            eval("?> $nav <?php ");
        ?>
        <div class="container my-3">
            <?php if(!Authenticate()){
                echo"You need to be logged in to access this page.";
            } else { ?>
            <nav class="mb-3">
                <div class="nav nav-pills" id="nav-tab" role="tablist">
                    <button class="nav-link" id="nav-diary-tab" data-bs-toggle="tab" data-bs-target="#nav-diary" type="button" role="tab" aria-controls="nav-diary" aria-selected="true">Diary</button>
                    <button class="nav-link" id="nav-create-tab" data-bs-toggle="tab" data-bs-target="#nav-create" type="button" role="tab" aria-controls="nav-create" aria-selected="false"><i class="bi bi-plus-circle"></i> New</button>
                </div>
            </nav>
            <div class="tab-content" id="nav-tabContent">
                <div class="tab-pane fade" id="nav-diary" role="tabpanel" aria-labelledby="nav-diary-tab">
                    <h1 class="display-4">Diary</h1>
                    <?php
                    echo"<p class='text-black-50'>Capture personal events and experiences, $wordcount words in total</p>";
                    
                    foreach($diary as $entry){
                        echo'<div class="rounded bg-dark p-1 my-3"></div>';
                        echo'<div class="text-center" data-id="' . $entry["id"] . '"  data-lang="' . $entry["lang"] . '">';
                            echo"<h1>" . $entry["title"] . "</h1>";
                            echo"<p class='fs-5 mb-2'>" . $entry["description"] . "</p>";
                            echo'<p class="text-black-50 mb-2">' . (DateTime::createFromFormat('Y-m-d H:i', $entry["date"])->format('D, j M Y H:i')) . ', ' . $entry["lang"] . ', ' . $entry["words"] . ' words' . '</p>';
                            echo"<a class='editbtn btn btn-sm btn-light me-1'><i class='bi bi-pencil-square'></i> Edit</a>";
                            echo"<a class='delbtn btn btn-sm btn-light'><i class='bi bi-trash3-fill'></i> Delete</a>";
                            echo"<div class='textcontent text-start mt-2'>";
                                foreach(explode("\n", $entry["content"]) as $bre){
                                    if(empty($bre)){
                                        echo"<br>";
                                    } else {
                                        echo"<span class='d-block'>$bre</span>";
                                    }
                                }
                            echo"</div>"; ?>
                            <form method="POST" class="row d-none">
                                <input type="hidden" name="to_update" value="<?php echo $entry["id"]; ?>">
                                <div class="mb-3 col-12 col-md-3">
                                    <label class="form-label">Title</label>
                                    <div class="grogol">
                                        <input type="text" name="update_title" value="<?php echo $entry["title"]; ?>" class="form-control mb-3">
                                        <label class="form-label">Language</label>
                                        <select required class="form-select mb-3" name="update_lang">
                                            <option value="GER">German</option>
                                            <option value="ENG">English</option>
                                        </select>
                                        <label class="form-label">Words</label>
                                        <input value="<?php echo $entry["words"]; ?>" required name="update_words" type="number" class="wordis form-control">
                                    </div>
                                </div>
                                <div class="mb-3 col-12 col-md-9">
                                    <label class="form-label">Description</label>
                                    <textarea name="update_description" class="desci form-control" rows="1"><?php echo $entry["description"]; ?></textarea>
                                </div>
                                <div class="mb-3 col-12">
                                    <label class="form-label">Content</label>
                                    <textarea name="update_content" required class="conti form-control" rows="10"><?php echo $entry["content"]; ?></textarea>
                                </div>
                                
                                <div class="mb-3 col-12">
                                    <button type="submit" class="btn btn-success"><i class="bi bi-arrow-repeat"></i> Update</button>
                                    <a class="btn btn-secondary" href="/diary/"><i class="bi bi-x-lg"></i> Cancel</a>
                                </div>
                            </form>
                        </div>
                    <?php } ?>
                </div>
                <div class="tab-pane fade" id="nav-create" role="tabpanel" aria-labelledby="nav-create-tab">
                    <form method="POST" class="row">
                        <div class="mb-3 col-12 col-md-3">
                            <label class="form-label">Title</label>
                            <div class="grogol">
                                <input type="text" name="title" class="form-control mb-3">
                                <label class="form-label">Language</label>
                                <select required class="form-select mb-3" name="lang">
                                    <option value="GER">German</option>
                                    <option value="ENG">English</option>
                                </select>
                                <label class="form-label">Words</label>
                                <input class="wordis form-control" value="0" required name="words" type="number">
                            </div>
                        </div>
                        <div class="mb-3 col-12 col-md-9">
                            <label class="form-label">Description</label>
                            <textarea class="desci form-control" name="description" rows="1"></textarea>
                        </div>
                        <div class="mb-3 col-12">
                            <label class="form-label">Content</label>
                            <textarea name="content" class="conti form-control" required rows="10"></textarea>
                        </div>
                        
                        <div class="mb-3 col-12">
                            <button type="submit" class="btn btn-success"><i class="bi bi-cloud-arrow-up"></i> Create</button>
                            <button type="button" id="hide" class="btn btn-secondary"><i class="bi bi-eye-slash"></i> Hide input</button>
                        </div>
                    </form>
                </div>
            </div>
                
            <?php } ?>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
        <script src="/js/chart.min.js"></script>
        <script src="/js/chartjs-adapter-date-fns.bundle.min.js"></script>
        <script>
            jQuery.fn.clickToggle = function(a, b) {
                return this.on("click", function(ev) { [b, a][this.$_io ^= 1].call(this, ev) });
            };

            $(document).ready( function () {
                $('#hide').clickToggle(function(){
                    $('.grogol input:first-child').css({"color": 'transparent'}).attr('spellcheck', false);
                    $('.desci').css({"color": 'transparent'}).attr('spellcheck', false);
                    $('.conti').css({"color": 'transparent'}).attr('spellcheck', false);
                    $('#hide').html('<i class="bi bi-eye"></i> Show input');
                }, function(){
                    $('.grogol input:first-child').css({"color": 'initial'}).attr('spellcheck', true);
                    $('.desci').css({"color": 'initial'}).attr('spellcheck', true);
                    $('.conti').css({"color": 'initial'}).attr('spellcheck', true);
                    $('#hide').html('<i class="bi bi-eye-slash"></i> Hide input');
                });

                if(localStorage.getItem('activeTabDiary') === null || localStorage.getItem('activeTabDiary') === "undefined"){
                    localStorage.setItem('activeTabDiary', $('div#nav-tab button').first().data('bs-target'));
                }
                $('div#nav-tab button').click(function(){
                    localStorage.setItem('activeTabDiary', $(this).data('bs-target'));
                    $('.desci').each(function(){
                        $(this).outerHeight($(this).parent().parent().find('.grogol').outerHeight() + "px");
                    });
                });
                $('#nav-tabContent ' + localStorage.getItem('activeTabDiary')).addClass('show active');
                $('#nav-tab ' + localStorage.getItem('activeTabDiary') + '-tab').addClass('active');

                $('.desci').each(function(){
                    $(this).outerHeight($(this).parent().parent().find('.grogol').outerHeight() + "px");
                });

                $(".conti").each(function(){
                    $(this).change(function() {
                        if($(this).val().trim().length !== 0){
                            $(this).parent().parent().find(".wordis").val($(this).val().split(/[\s\.\?]+/).length);
                        } else {
                            $(this).parent().parent().find(".wordis").val(0);
                        }
                    });
                });

                $('.editbtn').click(function(){
                    $(this).parent().removeClass('text-center');
                    if($(this).parent().data('lang') == 'ENG'){
                        $(this).parent().html($(this).parent().find('.d-none').removeClass('d-none')).find('select').val('ENG');
                    } else {
                        $(this).parent().html($(this).parent().find('.d-none').removeClass('d-none')).find('select').val('GER');
                    }
                    $('.desci').each(function(){
                        $(this).outerHeight($(this).parent().parent().find('.grogol').outerHeight() + "px");
                    });

                    $(".conti").each(function(){
                        $(this).change(function() {
                            if($(this).val().trim().length !== 0){
                                $(this).parent().parent().find(".wordis").val($(this).val().split(/[\s\.\?]+/).length);
                            } else {
                                $(this).parent().parent().find(".wordis").val(0);
                            }
                        });
                    });
                });

                $('.delbtn').click(function(){
                    if(confirm("Are you sure you want to delete this entry?")){
                        let s = "<form class='d-none' method='POST'><input type='number' name='to_delete' value='" + $(this).parent().data('id') + "'></form>";
                        let htmlObject = document.createElement('div');
                        htmlObject.innerHTML = s;
                        let newi = htmlObject.firstChild;
                        document.body.appendChild(newi);
                        newi.submit();
                    }
                });
            });
        </script>
    </body>
</html>