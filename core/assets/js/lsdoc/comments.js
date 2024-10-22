const CommentSystem = (function (){
    let commentItem = {
        itemId: null,
        itemType: 'document'
    };

    function exportCSV (){
        const url = "/salt/case/export_comment/"+commentItem.itemType+"/"+commentItem.itemId+"/comment.csv";
        window.location = url;
    }

    function init (nodeRef){
        setItem(nodeRef);
        $('.js-comments-container').comments({
            profilePictureUrl: '',
            enableDeletingCommentWithReplies: true,
            enableAttachments: COMMENT_ATTACHMENTS,
            getComments: function (success, error) {
                $.ajax({
                    type: 'get',
                    url: '/comments/'+commentItem.itemType+'/'+commentItem.itemId,
                    success: function (data) {
                        if (typeof data !== 'object') {
                            data = [];
                        }
                        success(data);
                    },
                    error: error
                });
            },
            postComment: function (commentJSON, success, error) {
                $.ajax({
                    type: 'post',
                    url: '/comments/'+commentItem.itemType+'/'+commentItem.itemId,
                    data: appendItemId(commentJSON),
                    success: function (comment) {
                        success(comment);
                    },
                    statusCode: {
                        401: function () {
                            window.location.href = '/login';
                        }
                    },
                    error: error
                });
            },
            putComment: function (commentJSON, success, error) {
                $.ajax({
                    type: 'put',
                    url: '/comments/' + commentJSON.id,
                    data: appendItemId(commentJSON),
                    success: function (comment) {
                        success(comment);
                    },
                    statusCode: {
                        401: function () {
                            window.location.href = '/login';
                        }
                    },
                    error: error
                });
            },
            deleteComment: function (commentJSON, success, error) {
                $.ajax({
                    type: 'delete',
                    url: '/comments/delete/' + commentJSON.id,
                    success: success,
                    statusCode: {
                        401: function () {
                            window.location.href = '/login';
                        }
                    },
                    error: error
                });
            },
            upvoteComment: function (commentJSON, success, error) {
                const commentURL = '/comments/' + commentJSON.id + '/upvote';

                if (commentJSON.user_has_upvoted) {
                    $.ajax({
                        type: 'post',
                        url: commentURL,
                        data: {
                            comment: commentJSON.id
                        },
                        success: function (comment) {
                            success(comment);
                        },
                        statusCode: {
                            401: function () {
                                window.location.href = '/login';
                            }
                        },
                        error: error
                    });
                } else {
                    $.ajax({
                        type: 'delete',
                        url: commentURL,
                        data: {
                            comment: commentJSON.id
                        },
                        success: function (comment) {
                            success(comment);
                        },
                        statusCode: {
                            401: function () {
                                window.location.href = '/login';
                            }
                        },
                        error: error
                    });
                }
            },
            uploadAttachments: function (commentArray, success, error) {
                let responses = 0;
                let successfulUploads = [];

                const serverResponded = function () {
                    responses++;

                    if (responses == commentArray.length) {
                        if (successfulUploads.length == 0) {
                            error();
                        } else {
                            success(successfulUploads);
                        }
                    }
                }

                $(commentArray).each(function (index, commentJSON) {
                    let formData = new FormData();

                    $(Object.keys(commentJSON)).each(function (index, key) {
                        const value = commentJSON[key];
                        if (value) {
                            formData.append(key, value);
                        }
                    });

                    $.ajax({
                        url: '/comments/'+commentItem.itemType+'/'+commentItem.itemId,
                        type: 'POST',
                        data: formData,
                        cache: false,
                        contentType: false,
                        processData: false,
                        success: function (commentJSON) {
                            successfulUploads.push(commentJSON);
                            serverResponded();
                        },
                        error: function (data) {
                            serverResponded();
                        }
                    });
                });
            }
        });
    }

    function setItem (nodeRef){
        if( nodeRef ){
            commentItem.itemId = nodeRef.id;
            commentItem.itemType = nodeRef.nodeType;
        } else {
            commentItem.itemId = $('.js-comments-container').data('lsdocid');
        }
    }

    function appendItemId (data){
        return $.extend(data, commentItem);
    }

    return {
        init: init,
        exportCSV: exportCSV
    };
})();

globalThis.CommentSystem = CommentSystem;

$(function(){
    CommentSystem.init();
});


