<?php

use tp\TouchPointWP\Person;

/** @var $person Person */
/** @var $iid int */
/** @var $btnClass string */

$image = get_avatar_url($person->id, ['width' => 200, 'height' => 300] );

if (!empty($image)) {
    $image = " style=\"background-image: url('$image');\"";
}

?>

<article id="person-<?php echo $person->peopleId; ?>" class="person-list-item" data-tp-person="<?php echo $person->peopleId ?>"<?php echo $image ?>>
    <header class="entry-header">
        <div class="entry-header-inner">
            <?php
            if ($person->hasProfilePage()) {
                /** @noinspection HtmlUnknownTarget */
                echo sprintf(
                    '<h2 class="entry-title default-max-width heading-size-1"><a href="%s">%s</a></h2>',
                    esc_url($person->getProfileUrl()),
                    esc_html($person->display_name)
                );
            } else {
                echo sprintf(
                    '<h2 class="entry-title default-max-width heading-size-1">%s</h2>',
                    esc_html($person->display_name)
                );
            }
        ?>
        </div>
        <div class="post-meta-single post-meta-single-top">
            <span class="post-meta">
                <?php
                if (isset($iid)) {
                    $membership = $person->getInvolvementMemberships($iid);
                    if ($membership !== null) {
                        echo $membership->description;
                    }
                }
                ?>
            </span><!-- .post-meta -->
        </div>
    </header><!-- .entry-header -->
    <div class="entry-content">
        <?php //echo wp_trim_words($person->description, 20, "..."); ?>
    </div>
    <div class="actions person-actions">
        <?php echo $person->getActionButtons("person-list", $btnClass); ?>
    </div>
</article>