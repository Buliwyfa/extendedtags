<?php

namespace humhub\modules\extendedtags\controllers;

use humhub\modules\extendedtags\models\forms\TakeSurvey;
use yii;
use humhub\modules\extendedtags\models\Tag;
use humhub\modules\extendedtags\models\forms\AddTags;
use humhub\modules\extendedtags\models\forms\RemoveTags;
use humhub\modules\extendedtags\models\forms\ImportTags;
use humhub\modules\extendedtags\models\SurveyPreferences;
use humhub\modules\extendedtags\lib;
use yii\helpers\Url;
use humhub\modules\user\models\User;

class UserController extends \humhub\components\Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'acl' => [
                'class' => \humhub\components\behaviors\AccessControl::className(),
            ]
        ];
    }

    /**
     * Configuration Action for Super Admins
     */
    public function actionIndex()
    {
        return $this->actionAdd();
    }

    /**
     * Action for adding tags to tagset
     */
    public function actionAdd()
    {
        $user = Yii::$app->user->getIdentity();
        $model = new AddTags();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {

            // rejects tags that are already in user's tagset
            if (!in_array(strtolower($model->tag), $tags = explode(", ", $user->tags)) && $model->tag != "") {

                // checks if tag is quantitative and avoid db vocabulary lookup if so
                if (strpos($model->tag, 'beds:') !== false || strpos($model->tag, 'baths:') !== false ||
                    strpos($model->tag, 'cars:') !== false ){
                    $user->tags = $user->tags . ", " . $model->tag;
                    $user->save();
                } else{
                    // check if duplicates exist within db
                    $duplicate = Tag::find()
                        ->where(['tag' => $model->tag])
                        ->count();

                    // rejects entry of tags not in vocabulary
                    if ($duplicate < 1){
                        // currently allows any tag to be entered for the sake of development ease
                        $user->tags = $user->tags . ", " . strtolower($model->tag);
                        $user->save();
                        //Yii::$app->getSession()->setFlash('error', "The entered tag is not accepted.");
                    }else{
                        // NOTE: current allowing any tag to be entered for the sake of development convenience
                        // when the platform goes live this will need to be disabled
                        $user->tags = $user->tags . ", " . strtolower($model->tag);
                        $user->save();
                    }
                }
            } else{
                Yii::$app->getSession()->setFlash('error', "The entered tag already exists.");
            }

            // clears text field content
            $model = new AddTags();
        }

        return $this->render('add', Array('model' => $model));
    }

    /**
     * Action for removing tags from tagset
     */
    public function actionRemove()
    {
        $user = Yii::$app->user->getIdentity();
        $model = new RemoveTags();

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->tags != ""){
            $newTagList = explode(", ", $user->tags);

            foreach ($model->tags as $toRemove) {
                unset($newTagList[$toRemove]);
            }

            $user->tags = implode(", ", $newTagList);
            $user->save();

            // deselects all checkbox items
            $model = new RemoveTags();
        }

        return $this->render('remove', Array('model' => $model));
    }

    /**
     * Action for taking tagset generation survey
     * TODO: make this functional
     */
    public function actionSurvey()
    {
        $user = Yii::$app->user->getIdentity();
        $model = new TakeSurvey();

        if ($model->load(Yii::$app->request->post()) && $model->validate()){
            $prefs = new SurveyPreferences($model);

            if ($prefs->getBathrooms() == 2){
                print_r($model->outputPrefs());
                exit();
            } else{
                print_r("bad");
                exit();
            }
        }

        return $this->render('survey', Array('model' => $model));
    }

    /**
     * Action for importing tag vocabulary from csv
     */
    public function actionImport()
    {
        $form = new ImportTags();

        return $this->render('import', array(
            'model' => $form
        ));
    }

    /**
     * Action to perform upload of tagset from file
     */
    public function actionUpload()
    {
        require_once(dirname(__FILE__) . "/../lib/parsecsv.lib.php");
        $csv = new \parseCSV();
        $model = new ImportTags();

        $validImports = array();
        $invalidImports = array();

        if(isset($_POST['ImportTags'])){
            $model->attributes=$_POST['ImportTags'];

            if(!empty($_FILES['ImportTags']['tmp_name']['csv_file'])){

                $file = \yii\web\UploadedFile::getInstance($model,'csv_file');
                $group_id = 1;

                $csv->auto($file->tempName);

                foreach($csv->data as $data) {
                    // check if duplicates exist within db
                    $duplicates = Tag::find()
                        ->where(['tag' => $data['tag']])
                        ->count();

                    // if no duplicates exist then inset into db
                    if(!$duplicates){
                        $importData = $data;
                        $tagModel = new Tag();
                        $tagModel->tag = $importData['tag'];

                        if($tagModel->save()){
                            $validImports[] = $importData;
                        }else{
                            $invalidImports[] = $importData;
                        }
                    }else{
                        $invalidImports[] = $data;
                    }
                }
            }
        }

        // render page listing valid and invalid tag imports
        return $this->render('import_complete', array(
            'validImports' => $validImports,
            'invalidImports' => $invalidImports
        ));
    }
}
