<?php
    session_start();

    $texts = json_decode(file_get_contents('../../../../json/writings.json'), true)['texts'];
    $users = json_decode(file_get_contents('../../../../json/users.json'), true)['users'];
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
    if(isset($_GET["id"])){
        $text = null;
        foreach($texts as $lole){
            if($lole["id"] == $_GET["id"]){
                $text = $lole;
                break;
            }
        }
    }

    if(Authenticate() && isset($_POST["title"]) && isset($_POST["lang"]) && isset($_POST["words"]) && isset($_POST["content"]) && isset($_POST["date"]) && isset($_POST["access"])) {
        $newtext["id"] = intval($_POST["id"]);
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

        foreach ($texts as $key => $field) {
            if ($field['id'] == $newtext["id"]) {
                $texts[$key] = $newtext;
            }
        }

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
                    echo"You need to be logged in to access this page.";
                } elseif(!isset($_GET["id"])){
                    echo"Please select a writing.";
                } else {
                ?>
                    <div class="d-flex flex-row-reverse">
                        <a id='delbtn' class='btn btn-danger'><i class="bi bi-trash3-fill"></i> Delete</a>
                        <a class="btn btn-secondary me-1" id="cancel"><i class="bi bi-x-lg"></i> Cancel</a>
                    </div>
                    <form method="POST" class="row">
                        <div class="mb-3 col-6 col-sm-6">
                            <label class="form-label">Title</label>
                            <input required name="title" type="text" class="form-control" value="<?php echo($text["title"]) ?>">
                        </div>
                        <div class="mb-3 col-6 col-sm-3">
                            <label class="form-label">Series</label>
                            <input name="series" type="text" class="form-control" value="<?php echo($text["series"]) ?>">
                        </div>
                        <div class="mb-3 col-6 col-sm-3">
                            <label class="form-label">Labels</label>
                            <input name="labels" type="text" class="form-control" value="<?php echo(implode(", ",$text["labels"])) ?>">
                        </div>
                        <div class="mb-3 col-6 col-sm-3">
                            <label class="form-label">Language</label>
                            <select required class="form-select" name="lang">
                            <?php
                                if($text["lang"] == "German"){
                                    echo'<option selected value="German">German</option>';
                                    echo'<option value="English">English</option>';

                                } else{
                                    echo'<option value="German">German</option>';
                                    echo'<option selected value="English">English</option>';
                                }
                            ?>
                            </select>
                        </div>
                        <div class="mb-3 col-4 col-sm-3">
                            <label class="form-label">Access level</label>
                            <select required class="form-select" name="access">
                            <?php
                                if($text["access"] == "private"){
                                    echo'<option selected value="private">private</option>';
                                    echo'<option value="public">public</option>';

                                } else{
                                    echo'<option value="private">private</option>';
                                    echo'<option selected value="public">public</option>';
                                }
                            ?>
                            </select>
                        </div>
                        <input type="hidden" name="id" value="<?php echo($text["id"]) ?>">
                        <div class="mb-3 col-5 col-sm-4">
                            <label class="form-label">Date</label>
                            <input required class="form-control" type="date" name="date" value="<?php echo($text["date"]) ?>">
                        </div>
                        <div class="mb-3 col-3 col-sm-2">
                            <label class="form-label">Words</label>
                            <input id="wordsinput" required name="words" type="number" class="form-control" value="<?php echo($text["words"]) ?>">
                        </div>
                        <div class="mb-3 col-12">
                            <label class="form-label">Content</label>
                            <textarea id="contentarea" required name="content" class="form-control" rows="20"><?php echo(str_replace("\n", "&#10;", $text["content"])); ?></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success"><i class="bi bi-arrow-repeat"></i> Update</button>
                        </div>
                    </form>
                <?php
                }
            ?>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
        <script>
            $(document).ready( function () {
                $("#contentarea").change(function() {
                    $("#wordsinput").val($(this).val().split(/[\s\.\?]+/).length);
                });
                $("#delbtn").click(function() {
                    if(confirm("Are you sure that you want to delete this writing?")){
                        location.href="<?php echo("../delete/?id=".$text["id"]); ?>";
                    }
                });
                $("#cancel").attr("href", "<?php echo("../?id=".$text["id"]); ?>");
            } );
        </script>
    </body>
</html>