<?php
/**
 *Plugin for limesurvey : Use your own string and text when user trye to enter a survey : not started/ expired / bad token.
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2016 Denis Chenu <http://www.sondages.pro>
 * @copyright 2016 Advantages <http://www.advantages.pro>
 * @license AGPL v3
 * @version 0.0.3
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
class adaptEnterErrorSurveyText extends \ls\pluginmanager\PluginBase {
    protected $storage = 'DbStorage';

    static protected $description = 'Use your own string and text when user trye to enter a survey : not started, expired or bad token.';
    static protected $name = 'adaptEnterErrorSurveyText';

  /**
   * The settings for this plugin
   * @Todo Move this to the constructor to add all language of system
   * @Todo Add the script for ckeditor
   */
  protected $settings=array(
    "useCompletedTemplate"=>array(
      'type'=>'checkbox',
      'label'=>'Use completed.pstpl template file to render the text',
      'help'=>'If you not activate this : the message is render with %s.',
      'htmlOptions'=>array(
        'uncheckValue'=>'',
      ),
      'default'=>'1'
    ),
  );

  /**
   * @var array $errorTexts The array of settings key with their english label n(for translation
   * @todo : us it in contructor to contruct $this->settings
   */
  private $errorTexts=array(
    'errorTextNotStarted'=>'Error html text to be shown when a respondant try to enter a not started survey',
    'errorTextExpired'=>'Error html text to be shown when a respondant try to enter an expired survey',
    'errorTextUsedToken'=>'Error html text to be shown when a respondant try to enter a survey with an already used token',

    /* this part need to be in beforeController event */
    //~ 'errorTextBadToken'=>'Error html text to be shown when a respondant try to enter a survey with a bad token',
    //~ 'errorTextNotExist'=>'Error html text to be shown when a respondant try to enter a unexisting survey',
    //~ 'errorTextNotActive'=>'Error html text to be shown when a respondant try to enter a survey not activated (if empty : show the default 404 error).',
  );

  /**
   * @var array[] $translation The array of translation string
   */
  private $translation=array(
    'Error html text to be shown when a respondant try to enter a not started survey'=>array('fr'=>"Texte à montrer quand le questionnaire n'est pas commencé"),
    'Error html text to be shown when a respondant try to enter an expired survey'=>array('fr'=>"Texte à montrer quand le questionnaire est terminé"),
    'Error html text to be shown when a respondant try to enter a unexisting survey'=>array('fr'=>"Texte à montrer si le questionnaire n'exite pas ou plus"),
    'Error html text to be shown when a respondant try to enter a survey with an already used token'=>array('fr'=>"Texte à montrer si le code d'invitation est déjà utilisé"),
    'Error html text to be shown when a respondant try to enter a survey with a bad token'=>array('fr'=>"Texte à montrer en cas de mauvaise saisie du jeton"),

    'HTML text to be shown in %s.'=>array('fr'=>"Code HTML à utiliser pour la langue %s."),
    'Use completed.pstpl template file to render the text'=>array('fr'=>"UItiliser le fichier completed.pstpl pour le rendu du texte"),
    'If you not activate this : the message is render with %s.'=>array('fr'=>"Si vous ne cochez pas cette case, le rendu utilisera ce code : %s."),
  );

  /**
   * @var array $language language to be used in public
   */
  private $language;


  public function init()
  {
    $this->subscribe('beforeSurveyPage');
    $this->subscribe('beforeSurveySettings');
    $this->subscribe('newSurveySettings');
  }

  public function beforeSurveySettings()
  {
    $oEvent = $this->event;
    $newSettings=array();
    $oSurvey=Survey::model()->findByPk($oEvent->get('survey'));
    $aLang=$oSurvey->getAllLanguages();

    $aCurrent=array();
    foreach($this->errorTexts as $setting=>$label)
    {
      $aCurrent[$setting]=$this->get($setting,'Survey',$oEvent->get('survey'),array());
      $aDefaultSettings[$setting]=$this->get($setting);
    }
    $apiVersion=explode(".",App()->getConfig("versionnumber"));
    if($apiVersion[0]>=2 && $apiVersion[1]>=50)
    {
      $cssUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/fix.css');
      Yii::app()->clientScript->registerCssFile($cssUrl);
    }
    foreach($aLang as $sLang)
    {
      $newSettings["title-$sLang"]=array(
          'type'=>'info',
          'content'=>"<strong class='label label-info'>".sprintf($this->gT("HTML text to be shown in %s."),$sLang)."</strong>",
      );
      foreach($this->errorTexts as $setting=>$label)
      {
        $newSettings["{$setting}-{$sLang}"]=array(
          'type'=>'html',
          'label'=>$this->gT($label),
          'htmlOptions'=>array(
            'class'=>'form-control',
            'height'=>'6em',
          ),
          'height'=>'6em',
          'editorOptions'=>array(
            "font-styles"=> false,
            "html"=> true,
            "link"=> false, // broken in LS
            "image"=> false, // broken in LS
            "lists"=>false,
          ),
          'localized'=>true,
          'language'=>$sLang,
          'current'=>$aCurrent[$setting],
        );
      }
    }
    $oEvent->set("surveysettings.{$this->id}", array(
      'name' => get_class($this),
      'settings' => $newSettings
    ));

  }

  public function newSurveySettings()
  {
    $event = $this->event;
    $aLangSettings=array();
    foreach ($event->get('settings') as $name => $value)
    {
      if(strpos($name,"-"))
      {
        $aNameLang=explode("-",$name);
        $name=$aNameLang[0];
        if(isset($aNameLang[2])){
          $lang="{$aNameLang[1]}-{$aNameLang[2]}";
        }else{
          $lang="{$aNameLang[1]}";
        }
        if(!isset($aLangSettings[$name]))
        {
          $aLangSettings[$name]=array();
        }
        $aLangSettings[$name][$lang]=$value[$lang];
      }
      else
      {
        /* In order use survey setting, if not set, use global, if not set use default */
        $this->set($name, $value, 'Survey', $event->get('survey'));
      }
    }
    foreach($aLangSettings as $setting=>$aValues)
    {
      $this->set($setting, $aValues, 'Survey', $event->get('survey'));
    }
  }

  public function beforeSurveyPage()
  {
    $oEvent = $this->event;
    $iSurveyId = $oEvent->get('surveyId');
    $oSurvey=Survey::model()->findByPk($iSurveyId);
    $this->setLanguage();
    /* We are sure the survey exist .... */
    if($oSurvey && !in_array(Yii::app()->request->getQuery('action',''),array('previewquestion','previewgroup')))
    {
      if($oSurvey->active=="Y")
      {
        $this->ThrowExpired();
        $this->ThrowNotStarted();
        $this->ThrowToken();
      }
    }
  }

  public function ThrowExpired()
  {
    $iSurveyId = $this->event->get('surveyId');
    $oSurvey=Survey::model()->findByPk($this->event->get('surveyId'));
    if($oSurvey->expires && $oSurvey->expires < dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust")))
    {
      $this->renderError('errorTextExpired');
    }
  }
  public function ThrowNotStarted()
  {
    $iSurveyId = $this->event->get('surveyId');
    $oSurvey=Survey::model()->findByPk($this->event->get('surveyId'));
    if($oSurvey->startdate && $oSurvey->startdate >= dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust")))
    {
      $this->renderError('errorTextNotStarted');
    }
  }
  public function ThrowToken()
  {
    $iSurveyId = $this->event->get('surveyId');
    $oSurvey=Survey::model()->findByPk($this->event->get('surveyId'));
    if(tableExists("{{tokens_".$iSurveyId."}}"))
    {
      $token=Yii::app()->request->getParam('token','');
      if($token)
      {
        $oToken=Token::model($iSurveyId)->find("token=:token",array(':token' => $token));
        $now = dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust"));
        tracevar(array(
          strtotime($now) < strtotime($oToken->validfrom),
          strtotime($now),
          strtotime($oToken->validfrom)

          )
        );
        if(!$oToken)
        {
          // @todo
        }
        elseif($oToken->completed!="N" && $oSurvey->alloweditaftercompletion != 'Y')
        {
          $this->renderError('errorTextUsedToken');
        }
        elseif($oToken->validfrom && strtotime($now) < strtotime($oToken->validfrom))
        {
          $this->renderError('errorTextNotStarted');
        }
        elseif($oToken->validuntil && strtotime($now) > strtotime($oToken->validuntil))
        {
          $this->renderError('errorTextExpired');
        }
      }
    }
    //~ if($oSurvey->startdate && $oSurvey->startdate >= dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust")))
    //~ {
      //~ $this->renderError('errorTextNotStarted');
    //~ }
  }
  /**
   * Set the language to be used
   * @return void
   */
  public function setLanguage()
  {
    $iSurveyId = $this->event->get('surveyId');
    $sLanguage=Yii::app()->request->getParam('lang','');
    if(empty($sLanguage) && isset($_SESSION['survey_'.$iSurveyId]['s_lang']))
    {
        $sLanguage = $_SESSION['survey_'.$iSurveyId]['s_lang'];
    }
    if(!in_array($sLanguage,Survey::model()->findByPk($iSurveyId)->getAllLanguages()))
    {
      $sLanguage=Survey::model()->findByPk($this->event->get('surveyId'))->language;
    }
    $this->language=$sLanguage;
  }
  /**
   * render the error to be shown
   * @param $setting : the setting to be used
   *
   * return @void
   */
  public function renderError($setting)
  {
    $iSurveyId = $this->event->get('surveyId');
    $aSurveyMessages=array_filter($this->get($setting,'Survey',$this->event->get('surveyId'),array()));
    $aDefaultMessages=$this->get($setting,null,null,array());
    $aMessage=array_merge($aDefaultMessages,$aSurveyMessages);
    if(empty($aMessage[$this->language]) || !trim($aMessage[$this->language]))
    {
      return;
    }
    Yii::app()->setConfig('surveyID',$iSurveyId); // For templatereplace
    $oSurvey=Survey::model()->findByPk($this->event->get('surveyId'));
    // Set the language for templatereplace
    SetSurveyLanguage($iSurveyId, $this->language);
    $lsVersion=App()->getConfig("versionnumber");
    $aVersion=explode(".",$lsVersion);
    if($aVersion[0]==2 && $aVersion[1]<=6)
    {
      $this->renderMessage206($aMessage[$this->language],$oSurvey->template);
    }
    else
    {
      $this->renderMessage250($aMessage[$this->language],$oSurvey->template);
    }

    /* 2.06 quick system (no render ...) */

  }

  /**
   * render a public message for 2.06 and lesser
   */
  public function renderMessage206($message,$template)
  {
    $templateDir=Template::getTemplatePath($template);
    $renderData['message']=$message;
    App()->controller->layout='bare';
    $renderData['language']=$this->language;
    if (getLanguageRTL($this->language))
    {
      $renderData['dir'] = ' dir="rtl" ';
    }
    else
    {
      $renderData['dir'] = '';
    }
    $renderData['templateDir']=$templateDir;
    $renderData['useCompletedTemplate']=$this->get('useCompletedTemplate',null,null,$this->settings['useCompletedTemplate']['default']);
    Yii::setPathOfAlias('adaptEnterErrorSurveyText', dirname(__FILE__));
    App()->controller->render("adaptEnterErrorSurveyText.views.2_06.public",$renderData);
    App()->end();
  }
  /**
   * render a public message for 2.50 and up
   */
  public function renderMessage250($message,$template)
  {
    $oTemplate = Template::model()->getInstance($template);
    $templateDir= $oTemplate->viewPath;
    $renderData['message']=$message;
    App()->controller->layout='bare';
    $renderData['language']=$this->language;
    if (getLanguageRTL($this->language))
    {
      $renderData['dir'] = ' dir="rtl" ';
    }
    else
    {
      $renderData['dir'] = '';
    }
    $renderData['templateDir']=$templateDir;
    $renderData['useCompletedTemplate']=$this->get('useCompletedTemplate',null,null,$this->settings['useCompletedTemplate']['default']);
    Yii::setPathOfAlias('adaptEnterErrorSurveyText', dirname(__FILE__));
    App()->controller->render("adaptEnterErrorSurveyText.views.2_50.public",$renderData);
    App()->end();
  }
  /**
   * Add the settings by lang
   * The core system is broken or hard to use : seems we can not have same setting with different language (the name is the key)
   */
  public function getPluginSettings($getValues=true)
  {
    $this->settings=parent::getPluginSettings($getValues);
    $restrictedToLanguage=trim(Yii::app()->getConfig('restrictToLanguages'));
    if(empty($restrictedToLanguage))
    {
      //~ $aLang=array_keys(getLanguageData(false, Yii::app()->getConfig('defaultlang')));
      //~ Yii::app()->setFlashMessage($this->gT("you have too many language, only default is shown."),'error');
      $aLang=array(Yii::app()->getConfig('defaultlang'));
    }
    else
    {
      $aLang=explode(' ',$restrictedToLanguage);
    }
    $this->settings["useCompletedTemplate"]["label"]=$this->gT($this->settings["useCompletedTemplate"]["label"]);
    $this->settings["useCompletedTemplate"]["help"]=sprintf($this->gT($this->settings["useCompletedTemplate"]["help"]),'<code>&lt;div id="wrapper"&gt;&lt;p id="token"&gt Your message &lt;p&gt;&lt;div&gt;</code>');

    $apiVersion=explode(".",App()->getConfig("versionnumber"));

    if($apiVersion[0]>=2 && $apiVersion[1]>=50)
    {
      $cssUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/fix.css');
      Yii::app()->clientScript->registerCssFile($cssUrl);
    }

    foreach($aLang as $sLang)
    {
      $this->settings["title{$sLang}"]=array(
          'type'=>'info',
          'content'=>"<strong class='label label-info'>".sprintf($this->gT("HTML text to be shown in %s."),$sLang)."</strong>",
      );

      foreach($this->errorTexts as $setting=>$label)
      {
        $this->settings["{$setting}-{$sLang}"]=array(
          // 'name'=>$setting, see bug report : https://bugs.limesurvey.org/view.php?id=11666
          'type'=>'html',
          'label'=>$this->gT($label),
          'height'=>'10em',
          'htmlOptions'=>array(
            'class'=>'form-control',
            'height'=>'15em',
          ),
          'editorOptions'=>array(
            "font-styles"=> false,
            "html"=> true,
            "link"=> false, // broken in LS
            "image"=> false, // broken in LS
          ),
          'localized'=>true,
          'language'=>$sLang,
        );
      }
      if($getValues)
      {
        foreach($this->errorTexts as $setting=>$label)
        {
          $this->settings["{$setting}-{$sLang}"]['current']=$this->get($setting,null,null,array());
        }
      }
    }

    return $this->settings;
  }
  /**
   * Fix language settings
   */
  public function saveSettings($settings)
  {
    $aFixedSettings=array();
    foreach ($settings as $name => $value)
    {
      if(strpos($name,"-"))
      {
        $aNameLang=explode("-",$name);
        if(!isset($aFixedSettings[$aNameLang[0]]))
        {
          $aFixedSettings[$aNameLang[0]]=array();
        }
        if(isset($aNameLang[2])){
          $lang="{$aNameLang[1]}-{$aNameLang[2]}";
        }else{
          $lang="{$aNameLang[1]}";
        }
        $aFixedSettings[$aNameLang[0]][$lang]=$value[$lang];
      }
      else
      {
        $aFixedSettings[$name]=$value;
      }
    }
    parent::saveSettings($aFixedSettings);
  }

  /**
   * quick language system
   */
  private function gT($string)
  {
    if(isset($this->translation[$string][Yii::app()->language]))
    {
      return $this->translation[$string][Yii::app()->language];
    }
    return $string;
  }
}
