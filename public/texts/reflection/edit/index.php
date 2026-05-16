<?php
    session_start();

    $reflections = json_decode(file_get_contents('../../../../json/reflections.json'), true)['reflections'];
    $questions = json_decode(file_get_contents('../../../../json/reflections.json'), true)['questions'];
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
        $reflection = null;
        foreach($reflections as $lole){
            if($lole["id"] == $_GET["id"]){
                $reflection = $lole;
                break;
            }
        }
    }

    if(Authenticate() && isset($_POST["title"]) && isset($_POST["lang"]) && isset($_POST["words"]) && isset($_POST["date"]) && isset($_POST["access"])) {
        $newtext["id"] = intval($_POST["id"]);
        $newtext["title"] = $_POST["title"];
        $newtext["date"] = $_POST["date"];
        $newtext["lang"] = $_POST["lang"];
        $newtext["words"] = intval($_POST["words"]);
        $newtext["access"] = $_POST["access"];

        foreach($questions as $question){
            if(isset($_POST[$question["shortcut"]])){
                $newtext['questions'][$question["shortcut"]] = str_replace("\r\n", "\n", $_POST[$question["shortcut"]]);
            }
        }

        foreach ($reflections as $key => $field) {
            if ($field['id'] == $newtext["id"]) {
                if($reflections[$key]["images"]){
                    $newtext["images"] = $reflections[$key]["images"];
                }
                $reflections[$key] = $newtext;
            }
        }

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
                    echo"You need to be logged in to access this page.";
                } elseif(!isset($_GET["id"])){
                    echo"Please select a reflection.";
                } else {
                ?>
                    <div class="d-flex flex-row-reverse">
                        <a id='delbtn' class='btn btn-danger'><i class="bi bi-trash3-fill"></i> Delete</a>
                        <a class="btn btn-secondary me-1" id="cancel"><i class="bi bi-x-lg"></i> Cancel</a>
                    </div>
                    <form method="POST" class="row">
                        <div class="mb-3 col-6 col-lg-4">
                            <label class="form-label">Title</label>
                            <input required name="title" type="text" class="form-control" value="<?php echo($reflection["title"]) ?>">
                        </div>
                        <div class="mb-3 col-6 col-lg-2">
                            <label class="form-label">Language</label>
                            <select required class="form-select" name="lang">
                            <?php
                                if($reflection["lang"] == "German"){
                                    echo'<option selected value="German">German</option>';
                                    echo'<option value="English">English</option>';

                                } else{
                                    echo'<option value="German">German</option>';
                                    echo'<option selected value="English">English</option>';
                                }
                            ?>
                            </select>
                        </div>
                        <div class="mb-3 col-4 col-lg-2">
                            <label class="form-label">Access level</label>
                            <select required class="form-select" name="access">
                            <?php
                                if($reflection["access"] == "private"){
                                    echo'<option selected value="private">private</option>';
                                    echo'<option value="public">public</option>';

                                } else{
                                    echo'<option value="private">private</option>';
                                    echo'<option selected value="public">public</option>';
                                }
                            ?>
                            </select>
                        </div>
                        <input type="hidden" name="id" value="<?php echo($reflection["id"]) ?>">
                        <div class="mb-3 col-5 col-lg-2">
                            <label class="form-label">Date</label>
                            <input required class="form-control" type="date" name="date" value="<?php echo($reflection["date"]) ?>">
                        </div>
                        <div class="mb-3 col-3 col-lg-2">
                            <label class="form-label">Words</label>
                            <input id="wordsinput" required name="words" type="number" class="form-control" value="<?php echo($reflection["words"]) ?>">
                        </div>
                        <?php
                            foreach($reflection["questions"] as $question => $answer){
                                echo'<div class="mb-3 col-12 col-md-6 col-xl-4">';
                                foreach($questions as $q){
                                    if($q["shortcut"] == $question){
                                        echo'<label class="form-label">'.$q["question"].'</label>';
                                        break;
                                    }
                                }
                                echo'<textarea required name="'.$question.'" class="form-control" rows="5">'.str_replace("\n", "&#10;", $answer).'</textarea>';
                                echo'</div>';
                            }
                        ?>
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
                $("#delbtn").click(function() {
                    if(confirm("Are you sure that you want to delete this reflection?")){
                        location.href="<?php echo("../delete/?id=".$reflection["id"]); ?>";
                    }
                });
                $("#cancel").attr("href", "<?php echo("../?id=".$reflection["id"]); ?>");
            } );
        </script>
    </body>
</html>