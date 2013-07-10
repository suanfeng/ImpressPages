/**
 * @package ImpressPages
 * @copyright   Copyright (C) 2011 ImpressPages LTD.
 *
 */

"use strict";

(function($) {

    var methods = {

        init : function(options) {

            return this.each(function() {
                var $this = $(this);
                var buyTab = this;

                var data = $this.data('ipRepositoryBuy');
                if (!data) {
                    $this.data('ipRepositoryBuy', {});

                    var $popup = $('.ipModuleRepositoryPopup');

                    $(window).bind("resize.ipRepositoryBuy", $.proxy(methods._resize, this));
                    $popup.bind('ipModuleRepository.close', $.proxy(methods._teardown, this));

                    //create crossdomain socket connection
                    var remote = new easyXDM.Rpc({
                        remote: $('#ipModuleRepositoryTabBuy').data('marketurl'),
                        container: "ipModuleRepositoryTabBuy",
                        onMessage: function(message, origin){
                            //DO NOTHING
                        },
                        onReady: function() {
                            //DO NOTHING
                        }
                    },
                    {
                        remote: {
                        },
                        local: {
                            selectFiles: function(files){
                                $.proxy(methods._confirm, this, [files])
                            }
                        }
                    }

                    );

                    $.proxy(methods._resize, this)();


                }
            });
        },


        _confirm : function (files) {
            $this.trigger('ipModuleRepository.confirm', [files]);
        },

        // set back our element
        _teardown: function() {
            $(window).unbind('resize.ipRepositoryBuy');
        },

        _resize: function(e) {
            var $this = $(this);
            $this.find('iframe').height((parseInt($(window).height()) - 55) + 'px');
        }

    };

    $.fn.ipRepositoryBuy = function(method) {
        if (methods[method]) {
            return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof method === 'object' || !method) {
            return methods.init.apply(this, arguments);
        } else {
            $.error('Method ' + method + ' does not exist on jQuery.ipRepositoryBuy');
        }

    };

})(jQuery);
