<?php defined('BASEPATH') or exit('No direct script access allowed');
ob_start();
$len = count($comments);
$i   = 0;

foreach ($comments as $comment) { ?>
<div class="comment-item"
    data-commentid="<?= e($comment->id); ?>">

    <?php if ($comment->staffid != 0) { ?>
    <a
        href="<?= admin_url('profile/' . $comment->staffid); ?>">
        <?= staff_profile_image($comment->staffid, [
            'staff-profile-image-small',
            'media-object img-circle pull-left mright10',
        ]);
        ?>
    </a>
    <?php } ?>
    <?php if ($comment->staffid == get_staff_user_id() || is_admin()) { ?>
    <div class="tw-flex tw-items-center tw-space-x-2 pull-right">
        <a href="#" class="text-muted edit-comment " data-id="<?= $comment->id ?>">
            <i class="fa-regular fa-pen-to-square"></i>
        </a>
        <a href="#" class="text-muted delete-comment" data-id="<?= $comment->id ?>">
            <i class="fa-regular fa-trash-can"></i>
        </a>
    </div>
    <?php } ?>
    <div class="media-body">
        <div class="mtop5">
            <?php if ($comment->staffid != 0) { ?>
            <a
                href="<?= admin_url('profile/' . $comment->staffid); ?>"><?= e(get_staff_full_name($comment->staffid)); ?></a>
            <?php } else { ?>
            <?= '<b>' . _l('is_customer_indicator') . '</b>'; ?>
            <?php } ?>
            <small class="text-muted text-has-action" data-toggle="tooltip"
                data-title="<?= e(_dt($comment->dateadded)); ?>">
                -
                <?= e(time_ago($comment->dateadded)); ?></small>
        </div>
        <div data-contract-comment="<?= e($comment->id); ?>"
            class="tw-mt-3 comment-content">
            <?= process_text_content_for_display($comment->content); ?>
        </div>
    </div>
    <?php if ($i >= 0 && $i != $len - 1) {
        echo '<hr />';
    }
    ?>
</div>
<?php $i++;
} ?>