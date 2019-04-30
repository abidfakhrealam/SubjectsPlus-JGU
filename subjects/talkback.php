<?php

use SubjectsPlus\Control\Querier;
use SubjectsPlus\Control\TalkbackService;
use SubjectsPlus\Control\TalkbackComment;
use SubjectsPlus\Control\MailMessage;
use SubjectsPlus\Control\Mailer;
use SubjectsPlus\Control\SlackMessenger;
use SubjectsPlus\Control\Template;
use SubjectsPlus\Control\ReCaptchaService;

include( "../control/includes/config.php" );
include( "../control/includes/functions.php" );
include( "../control/includes/autoloader.php" );

/**
 * global for the reply email address for subjectsplus administrator
 * @global $administrator_email
 */
global $administrator_email;

/**
 * global for MailMessage class
 * @global $talkback_to_address
 */
global $talkback_to_address;

/**
 *
 * @var $talkback_to_address_label
 */
global $talkback_to_address_label;

/**
 *
 * @var $talkback_subject_line
 */
global $talkback_subject_line;

/**
 * globals for Mailer class
 * @global $email_host
 */
global $email_host;

/**
 *
 * @var $email_port
 */
global $email_port;

/**
 *
 * @var $email_smtp_auth
 */
global $email_smtp_auth;

/**
 *
 * @var $email_smtp_debug
 */
global $email_smtp_debug;

/**
 * globals for ReCaptcha
 * @global $talkback_use_recaptcha
 */
global $talkback_use_recaptcha;

/**
 *
 * @var $talkback_recaptcha_secret_key
 */
global $talkback_recaptcha_secret_key;

/**
 *
 * @var $talkback_recaptcha_site_key
 */
global $talkback_recaptcha_site_key;

/**
 * globals for Slack Message
 * @global $talkback_use_slack
 */
global $talkback_use_slack;

/**
 *
 * @var $talkback_slack_channel
 */
global $talkback_slack_channel;

/**
 *
 * @var $talkback_slack_webhook_url
 */
global $talkback_slack_webhook_url;

/**
 *
 * @var $talkback_slack_emoji
 */
global $talkback_slack_emoji;


/**
 * Show headshots true/false
 * @global $talkback_show_headshot
 */
global $talkback_show_headshot;


/**
 * local variables used in subjectsplus header.php
 * @var $page_title
 */
$page_title = _( "Talk Back" );

/**
 *
 * @var $page_description
 */
$page_description = _( "Share your comments and suggestions about the library" );

/**
 *
 * @var $page_keywords
 */
$page_keywords = _( "library, comments, suggestions, complaints" );

/**
 * email address taken from comment form
 * @var $this_name
 */
$this_name = "";

/**
 * comment taken from comment form
 * @var $this_comment
 */
$this_comment = "";


/**
 *
 * @var $today
 */
$today = getdate();

/**
 *
 * @var $month
 */
$month = $today['month'];

/**
 *
 * @var $mday
 */
$mday = $today['mday'];

/**
 *
 * @var $year
 */
$year = $today['year'];

/**
 *
 * @var $this_year
 */
$this_year = date( "Y" );

/**
 *
 * @var $todaycomputer
 */
$todaycomputer = date( 'Y-m-d H:i:s' );

/**
 *
 * @var $insertCommentFeedback
 */
$insertCommentFeedback = "";

/**
 *
 * @var $insertCommentSuccessFeedback
 */
$insertCommentSuccessFeedback = _( "Thank you for your feedback.  We will try to post a response within the next three business days." );

/**
 *
 * @var $insertCommentFeedbackFail
 */
$insertCommentFeedbackFail = _( "There was a problem with your submission.  Please try again." ) . PHP_EOL;
$insertCommentFeedbackFail .= _( "If you continue to get an error, please contact the <a href='mailto:{$administrator_email}'>administrator</a>" );


/**
 *
 * @var $recaptcha_response
 */
$recaptcha_response = "";

/**
 *
 * @var $recaptcha_response_fail
 */
$recaptcha_response_fail = _("Recaptcha score is too low. Your comment was not submitted: ");

/**
 * this can be overriden below
 * @var $form_action
 */
$form_action = "talkback.php";

/**
 *
 * @var $set_filter
 */
$set_filter  = "";

/**
 *
 * @var $cat_filters
 */
$cat_filters = "";



if ( isset( $all_tbtags ) ) {

	// Let's get the first item off the tb array to use as our default
	reset( $all_tbtags ); // make sure array pointer is at first element
	$set_filter = key( $all_tbtags );


	// determine branch/filter
	if ( isset( $_REQUEST["v"] ) ) {
		$set_filter = scrubData( lcfirst( $_REQUEST["v"] ) );


		// Quick'n'dirty setup email recipients
		switch ( $set_filter ) {
			case "music":
				$page_title   = "Comments for the Music Library";
				$form_action  = "talkback.php?v=$set_filter";
				$tb_bonus_css = "talkback_form_music";
				break;
			case "rsmas":
				$page_title  = "Comments for the Marine Library";
				$form_action = "talkback.php?v=$set_filter";
				break;
			case "calder":
				$page_title = "Comments";
				$set_filter = "calder";
				// nothing, we just use the $administrator email on file (config.php)
				$form_action = "talkback.php";
				break;
			default:
				// nothing, we just use the $administrator email on file (config.php)
				$form_action = "talkback.php";
		}

		// override our admin email
		if ( isset( $all_tbtags[ $set_filter ] ) && $all_tbtags[ $set_filter ] != "" ) {
			$administrator_email = $all_tbtags[ $set_filter ];
		}

	}
}


$filter = '%' . $set_filter . '%';
if ( isset( $_GET['c'] ) ) {
	$cat_tags = '%' . scrubData( $_GET['c'] ) . '%';

} else {
	$cat_tags = "%%";

}

if ( isset( $_GET["t"] ) && $_GET["t"] == "prev" ) {
	$comment_year = 'prev';
	$comment_header =  _( "Comments from Previous Years" );
	$current_comments_link = "?v=".$set_filter;
	$current_comments_label = _( "See this year" );

} else {
	$comment_year = 'current';
	$comment_header = _( "Comments from " ) . $this_year;
	$current_comments_link = "?t=prev&v=".$set_filter;
	$current_comments_label = _( "See previous years" );
}

if ( isset( $all_cattags ) ) {

	/**
	 * @todo add a filter that displays all responses
	 */
	foreach ( $all_cattags as $value ) {
		if ( isset( $_GET["c"] ) && $value == $_GET["c"] ) {
			$tag_class  = "ctag-on";
			$cat_filter = $value;
		} else {
			$tag_class = "";
		}
		$cat_filters .= " <a href=\"talkback.php?v=$set_filter&c=$value\" class=\"$tag_class\">$value</a>";
	}
}



/**
 * init Querier to pass to TalkbackService class
 * @var $db
 */
$db = new Querier();

/**
 * init TalkbackService
 * requires an instance of Querier to be passed to the class
 * @var $talkbackService
 */
$talkbackService = new TalkbackService($db);

/**
 * Get Active Comments and Pass off to if/else block for use with template
 * @var $comments_response
 */
$comments_response = $talkbackService->getComments($comment_year, $this_year, $filter, $cat_tags);


/**
 * Set the $comments template var
 * @var $comments
 */
if(!empty($comments_response)) {
	$comments = $comments_response;
} else {
	$comments = _( "There are no comments just yet.  Be the first!" );
}




/**
 * init the $recaptcha_response var for use with the template after it's set in the if/else block
 * @var $recaptcha_response
 */
$recaptcha_response = "";

if ( isset( $_POST['the_suggestion'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recaptcha_response']) ) {

	// clean up post variables
	if ( isset( $_POST["name"] ) ) {
		$this_name = scrubData( $_POST["name"] );
	} else {
		$this_name = "Anonymous";
	}

	if ( isset( $_POST["the_suggestion"] ) ) {
		$this_comment = scrubData( $_POST["the_suggestion"] );
	} else {
		$this_comment = "";
	}


	/**
	 * TalkbackComment object
	 * @var $newComment
	 */
	$newComment = new TalkbackComment();
	$newComment->setQuestion( $this_comment );
	$newComment->setQFrom( $this_name );
	$newComment->setDateSubmitted( $todaycomputer );
	$newComment->setDisplay( 'No' );
	$newComment->setTbtags( $set_filter );
	$newComment->setAnswer( '' );


	if ( $talkback_use_recaptcha === true ) {

		/**
		 * init ReCaptchaService
		 * @var $recaptcha_service
		 */
		$recaptcha_service = new ReCaptchaService();
		$recaptcha_service->setServerName( scrubData( $_SERVER['SERVER_NAME'] ) );
		$recaptcha_service->setRemoteAddr( scrubData( $_SERVER['REMOTE_ADDR'] ) );
		$recaptcha_service->setAction( 'talkback' );
		$recaptcha_service->setToken( scrubData( $_POST['recaptcha_response'] ) );
		$recaptcha_response = $recaptcha_service->verify( $talkback_recaptcha_secret_key );


		// Take action based on the score returned:
		if ( $recaptcha_response->getScore() >= 0.5 ) {


			// If CAPTCHA is successful...
			// insert the new comment into the db and provide user feedback
			if( !$talkbackService->insertComment( $newComment ) ) {
				$insertCommentFeedback = $insertCommentSuccessFeedback;
			} else {
				$insertCommentFeedback = $insertCommentFeedbackFail;
			}


			if ( $talkback_use_email === true ) {

				/**
				 * create the html email template
				 * @var $tpl_name
				 * @var $tpl
				 * @var $html_message
				 */
				$tpl_name     = 'html_msg';
				$tpl          = new Template( './views/talkback' );
				$html_message = $tpl->render( $tpl_name, array(
					'this_name'    => $this_name,
					'this_comment' => $this_comment,
					'datetime'     => date( 'Y-m-d H:i:s' )

				) );

				/**
				 * configure MailMessage
				 * @var $mailMessege
				 */
				$mailMessege = new MailMessage();
				$mailMessege->setFromAddress( $this_name );
				$mailMessege->setFromLabel( $this_name );
				$mailMessege->setToAddress( $talkback_to_address );
				$mailMessege->setToAddressLabel( $talkback_to_address_label );
				$mailMessege->setSubject( $talkback_subject_line );
				$mailMessege->setMsgHTML( $html_message );


				/**
				 * configure Mailer and send email
				 * @var $mailer
				 */
				$mailer            = new Mailer( $mailMessege );
				$mailer->Host      = $email_host;
				$mailer->Port      = $email_port;
				$mailer->SMTPAuth  = $email_smtp_auth;
				$mailer->SMTPDebug = $email_smtp_debug;

				// provide user feedback for mail
				$mailer->send();
			}

			// set up a slack message
			if ( $talkback_use_slack === true ) {

				$msg = _( "New Comment via Talkback" ) . PHP_EOL;
				$msg .= "$this_comment" . PHP_EOL;
				$msg .= _( "From: " ) . $this_name . PHP_EOL;
				$msg .= _( "Date submitted: " ) . $todaycomputer . PHP_EOL;
				$msg .= _( "Tags: " ) . $set_filter . PHP_EOL;

				/**
				 * send comment to slack channel talkback
				 * @var $slackMsg
				 */
				$slackMsg = new SlackMessenger();
				$slackMsg->setChannel( $talkback_slack_channel );
				$slackMsg->setIcon( $talkback_slack_emoji );
				$slackMsg->setWebhookurl( $talkback_slack_webhook_url );
				$slackMsg->setMessage( $msg );
				$slackMsg->send();
			}

		} else {
			// Not verified - show form error
			$insertCommentFeedback = "Recaptcha score is too low. Your comment was not submitted: " . $recaptcha_response->getScore();

		}

	} else {
		/**
		 * @todo basic form without ReCaptcha
		 */
	}
}





/**
 * Include the header based on the theme used
 * @global $subjects_theme
 */
if ( isset( $subjects_theme ) && $subjects_theme != "" ) {
	include("./themes/{$subjects_theme}/views/talkback/page_metadata.php");
	include( "includes/header_{$subjects_theme}.php" );
} else {
	include( "includes/header.php" );
}


/**
 * Pass the template parameters to the public view template based on the theme used
 * @global $subjects_theme
 * @var $tpl_folder
 */

if ( isset( $subjects_theme ) && $subjects_theme != "" ) {
	$tpl_folder = "./themes/{$subjects_theme}/views/talkback";
} else {
	$tpl_folder = "./views/talkback";
}

/**
 *
 * @var $tpl_name
 */
$tpl_name = 'public';

/**
 *
 * @var $tpl
 */
$tpl = new Template( $tpl_folder );
echo $tpl->render( $tpl_name, array(
	'form_action'                 => $form_action,
	'comments'                    => $comments,
	'this_name'                   => $this_name,
	'this_comment'                => $this_comment,
	'talkback_show_headshot'      => $talkback_show_headshot,
	'cat_filters'                 => $cat_filters,
	'set_filter'                  => $set_filter,
	'comment_year'                => $comment_year,
	'comment_header'              => $comment_header,
	'current_comments_link'       => $current_comments_link,
	'current_comments_label'      => $current_comments_label,
	'insertCommentFeedback'       => $insertCommentFeedback,
	'talkback_recaptcha_site_key' => $talkback_recaptcha_site_key

) );

/**
 * Include the footer based on the theme used
 * @global $subjects_theme
 */
if ( isset( $subjects_theme ) && $subjects_theme != "" ) {
	include( "includes/footer_{$subjects_theme}.php" );
} else {
	include( "includes/footer.php" );
}