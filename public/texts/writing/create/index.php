<?php
    session_start();

    $users = json_decode(file_get_contents('../../../../json/users.json'), true)['users'];
    $texts = json_decode(file_get_contents('../../../../json/writings.json'), true)['texts'];
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

    if(Authenticate() && isset($_POST["title"]) && isset($_POST["lang"]) && isset($_POST["words"]) && isset($_POST["content"]) && isset($_POST["date"]) && isset($_POST["access"])) {
        $newtext["id"] = 0;
        foreach($texts as $text){
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
        $newtext["content"] = str_replace("\r\n", "\n", $_POST["content"]);

        if(!empty( $_POST["series"])){
            $newtext["series"] = $_POST["series"];
        } else{
            $newtext["series"] = false;
        }

        $labelarray = explode(", ", $_POST["labels"]);
        $newtext["labels"] = $labelarray;

        array_push($texts, $newtext);
        $gringo = array('texts'=>$texts);
        file_put_contents('../../../../json/writings.json', json_encode($gringo, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        
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
                    <form method="POST" class="row">
                        <div class="mb-3 col-12 col-sm-6">
                            <label class="form-label">Title</label>
                            <input required name="title" type="text" class="form-control">
                        </div>
                        <div class="mb-3 col-6 col-sm-3">
                            <label class="form-label">Series</label>
                            <input name="series" type="text" class="form-control">
                        </div>
                        <div class="mb-3 col-6 col-sm-3">
                            <label class="form-label">Labels</label>
                            <input name="labels" type="text" class="form-control">
                        </div>
                        <div class="mb-3 col-6 col-sm-3">
                            <label class="form-label">Language</label>
                            <select required class="form-select" name="lang">
                                <option value="German">German</option>
                                <option value="English">English</option>
                            </select>
                        </div>
                        <div class="mb-3 col-6 col-sm-3">
                            <label class="form-label">Access level</label>
                            <select required class="form-select" name="access">
                                <option value="private">private</option>
                                <option value="public">public</option>
                            </select>
                        </div>
                        <div class="mb-3 col-6 col-sm-4">
                            <label class="form-label">Date</label>
                            <input required class="form-control" type="date" name="date" value=<?php echo"'".(new DateTime())->format('Y-m-d')."'"; ?>>
                        </div>
                        <div class="mb-3 col-6 col-sm-2">
                            <label class="form-label">Words</label>
                            <input id="wordsinput" value="0" required name="words" type="number" class="form-control">
                        </div>
                        <div class="mb-3 col-12">
                            <label class="form-label">Content</label>
                            <textarea id="contentarea" required name="content" class="form-control" rows="15"></textarea>
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
            $("#contentarea").change(function() {
                if($(this).val().trim().length !== 0){
                    $("#wordsinput").val($(this).val().split(/[\s\.\?]+/).length);
                } else {
                    $("#wordsinput").val(0);
                }
            });
        </script>
    </body>
</html>