<?php
    session_start();

    $users = json_decode(file_get_contents('../../../../json/users.json'), true)['users'];
    $reflections = json_decode(file_get_contents('../../../../json/reflections.json'), true)['reflections'];
    $questions = json_decode(file_get_contents('../../../../json/reflections.json'), true)['questions'];
    $nav = file_get_contents('../../../nav');

    function Authenticate(){
        global $users;
        foreach($users as $lola){
            if(isset($_SESSION["username"]) && $_SESSION["username"] == $lola['username'] && $_SESSION["password"] == $lola['password']){
                return true;
            }
        }
        return false;
    }

    if(Authenticate() && isset($_POST["title"]) && isset($_POST["lang"]) && isset($_POST["words"]) && isset($_POST["date"]) && isset($_POST["access"])) {
        $newtext["id"] = 0;
        foreach($reflections as $text){
            if($text["id"] > $newtext["id"]){
                $newtext["id"] = $text["id"];
            }
        }
        $newtext["id"]++;

        $newtext["title"] = $_POST["title"];
        $newtext["date"] = $_POST["date"];
        $newtext["lang"] = $_POST["lang"];
        $newtext["words"] = intval($_POST["words"]);
        $newtext["access"] = $_POST["access"];

        foreach($questions as $question){
            if(isset($_POST[$question["shortcut"]]) && $_POST[$question["shortcut"]] != ""){
                $newtext['questions'][$question["shortcut"]] = str_replace("\r\n", "\n", $_POST[$question["shortcut"]]);
            }
        }

        $files = array_filter($_FILES["upload"]);
        $file_count = count($files['name']);
        if($files["name"][0] == ""){
            $file_count = 0;
        }
        for( $i=0 ; $i < $file_count ; $i++ ) {
            if ($files["size"][$i] > 2000000) {
                exit('<div class="container my-3">Error. Maximum file size is 2 MB.</div></body></html>');
            }

            if (file_exists("../../../../img/reflections/" . $files['name'][$i])) {
                exit('<div class="container my-3">Error. File already exists.</div></body></html>');
            }

            if(strtolower(pathinfo($files['name'][$i],PATHINFO_EXTENSION)) != "webp") {
                exit('<div class="container my-3">Error. Please only use webp format.</div></body></html>');
            }

            if (!move_uploaded_file($files["tmp_name"][$i], "../../../../img/reflections/" . $files['name'][$i])) {
                exit('<div class="container my-3">Error. Unknown.</div></body></html>');
            }

            $newtext["images"][] = $files['name'][$i];

        }

        array_push($reflections, $newtext);
        $gringo['reflections'] = $reflections;
        $gringo['questions'] = $questions;

        file_put_contents('../../../../json/reflections.json', json_encode($gringo, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        
        header("Location: /texts/");
        die();
    }
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
        <link rel="icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/favicon.ico">
    </head>
    <body>
        <?php eval("?> $nav <?php "); ?>
        <div class="container my-3">
            <?php
                if(!Authenticate()){
                    echo "You need to be logged in to access this page.";
                } else{ ?>
                    <div class="d-flex flex-row-reverse">
                        <a class="btn btn-secondary" href="/texts/"><i class="bi bi-x-lg"></i> Cancel</a>
                    </div>
                    <form method="POST" class="row" enctype="multipart/form-data">
                        <div class="mb-3 col-12 col-lg-4">
                            <label class="form-label">Title</label>
                            <input required name="title" type="text" class="form-control" value="<?php echo "Week " . ltrim((new DateTime())->format("W \of Y"),"0"); ?>">
                        </div>
                        <div class="mb-3 col-6 col-lg-2">
                            <label class="form-label">Language</label>
                            <select required class="form-select" name="lang">
                                <option value="English">English</option>
                                <option value="German">German</option>
                            </select>
                        </div>
                        <div class="mb-3 col-6 col-lg-2">
                            <label class="form-label">Access level</label>
                            <select required class="form-select" name="access">
                                <option value="private">private</option>
                                <option value="public">public</option>
                            </select>
                        </div>
                        <div class="mb-3 col-6 col-lg-2">
                            <label class="form-label">Date</label>
                            <input required class="form-control" type="date" name="date" value=<?php echo"'".(new DateTime())->format('Y-m-d')."'"; ?>>
                        </div>
                        <div class="mb-3 col-6 col-lg-2">
                            <label class="form-label">Words</label>
                            <input id="wordsinput" value="0" required name="words" type="number" class="form-control">
                        </div>
                        <?php
                            foreach($questions as $question){
                                if($question["active"] == true){ ?>
                                    <div class="mb-3 col-12 col-md-6 col-xl-4">
                                        <label class="form-label"><?php echo $question["question"]; ?></label>
                                        <textarea <?php if($question["required"] == true) { echo "required"; } ?> name="<?php echo $question["shortcut"]; ?>" class="form-control" rows="7"></textarea>
                                    </div>
                                <?php }
                            }
                        ?>
                        <div class="mb-3 col-12 col-md-6 col-xl-4">
                            <label class="form-label">Pictures</label>
                            <input name="upload[]" class="form-control" type="file" multiple>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success"><i class="bi bi-cloud-arrow-up" title="Create"></i> Create</button>
                        </div>
                    </form>
                <?php }
            ?>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
        <script>
            var words;
            $("textarea").change(function() {
                words = 0;
                $('textarea').each(function(){
                    if($(this).val().trim().length !== 0){
                        words+=$(this).val().split(/[\s\.\?]+/).length;
                    }
                })
                $("#wordsinput").val(words);
            });
        </script>
    </body>
</html>