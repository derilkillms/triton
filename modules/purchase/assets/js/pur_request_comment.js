// JavaScript for handling comments
$(document).ready(function () {
  var rel_id = window.location.pathname.split("/").pop(); // Ambil ID dari URL
  loadComments(rel_id);

  $("#add_comment_btn").click(function () {
    var content = $("#comment").val();
    if (content.trim() !== "") {
      $.post(
        admin_url + "purchase/add_request_comment/",
        { content: content, rel_id: rel_id, rel_type: "pur_request" },
        function (response) {
          if (response.success) {
            $("#comment").val("");
            loadComments(rel_id);
          }
        },
        "json"
      );
    }
  });

  // Klik tombol edit â†’ Munculkan form edit tepat di bawah komentar
  $(document).on("click", ".edit-comment", function () {
    var commentItem = $(this).closest(".comment-item");
    var commentId = $(this).data("id");
    var content = commentItem.find(".comment-content").text().trim();

    // Cek apakah form edit sudah ada
    var existingEditForm = commentItem.find(".edit-comment-form");

    if (existingEditForm.length > 0) {
        // Jika form sudah ada, toggle tampilannya (hide/show)
        existingEditForm.toggle();
    } else {
        // Jika belum ada, hapus semua form edit lain terlebih dahulu
        $(".edit-comment-form").remove();

        // Tambahkan form edit di bawah komentar yang dipilih
        var editForm = `
            <div class="edit-comment-form">
                <textarea class="form-control edit-comment-text">${content}</textarea>
                <p class="tw-mt-3 tw-mb-3">
                    <button class="btn btn-secondary cancel-edit-comment">Cancel</button>
                    <button class="btn btn-primary save-edit-comment" data-id="${commentId}">Update Comment</button>
                </p>
            </div>
        `;
        commentItem.append(editForm);
    }
});

  // Klik tombol batal → Sembunyikan form edit
  $(document).on('click', '.cancel-edit-comment', function() {
    $(this).closest('.edit-comment-form').hide();
});

  // Klik tombol simpan â†’ Kirim perubahan ke server
  $(document).on("click", ".save-edit-comment", function () {
    var commentId = $(this).data("id");
    var newContent = $(this)
      .closest(".edit-comment-form")
      .find(".edit-comment-text")
      .val();

    if (newContent.trim() !== "") {
      $.post(
        admin_url + "purchase/edit_request_comment/",
        { id: commentId, content: newContent },
        function (response) {
          if (response.success) {
            loadComments(rel_id); // Refresh komentar setelah update
          }
        },
        "json"
      );
    }
  });

  $(document).on("click", ".delete-comment", function () {
    var comment_id = $(this).data("id");
    if (confirm("Yakin ingin menghapus komentar ini?")) {
      $.post(
        admin_url + "purchase/delete_request_comment/",
        { id: comment_id },
        function (response) {
          if (response.success) {
            loadComments(rel_id);
          }
        },
        "json"
      );
    }
  });

  function loadComments(rel_id) {
    $.get(
      admin_url + "purchase/list_request_comment/" + rel_id,
      function (data) {
        $("#contract-comments").html(data);
        var totalComments = $('[data-commentid]').length;
       var commentsIndicator = $('.comments-indicator');
       if(totalComments == 0) {
            commentsIndicator.addClass('hide');
       } else {
         commentsIndicator.removeClass('hide');
         commentsIndicator.text(totalComments);
       }
      }
    );
  }
});
