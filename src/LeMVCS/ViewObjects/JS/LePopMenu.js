/**
 * @note For each menu item in the Pop Menu, there is one LePopMenuItemModel. These are created and
 *      injected into the LePopMenuModel that is then injected into LePopMenu.
 *
 *       See HOW-TO EXAMPLE at bottom of the page
 *
 * @param text string
 * @param callBack function
 * @param attrs {{}}
 * @constructor
 */
function LePopMenuItemModel (text, callBack, attrs = {}) {
    this.text = text;
    this.callBack = callBack;
    this.attr = attrs;
}

/**
 * @note This method is passed into LePopMenu
 *
 *      See HOW-TO EXAMPLE at bottom of the page
 *
 * @param elemTarget $() the element the menu is appended too
 * @param menuClass - string for css class
 * @param menuItems - array LePopMenuItemModel's
 * @param menuHorOffset - integer to offset the menu from the parent element or the clicked element
 *              so the button would be the clicked element and the parent would be the containing DOM element
 * @param menuVertOffset - setinteger to offset the menu from the parent element or the clicked element
 *              so the button would be the clicked element and the parent would be the containing DOM element
 * @param attr {{}}
 * @param clear {boolean}
 * @constructor
 */
function LePopMenuModel (
    elemTarget,
    menuClass,
    menuItems,
    menuHorOffset = 0,
    menuVertOffset = 0,
    attr = {},
    clear = false
) {
    this.elemTarget = elemTarget;
    this.menuClass = menuClass;
    this.menuItems = menuItems;
    this.menuHorOffset = menuHorOffset;
    this.menuVertOffset = menuVertOffset;
    this.attr = attr;
    this.clear = clear; // completely remove the existing menu
}

/**
 * @param options {LePopMenuModel}
 * @constructor
 */
$.fn.LePopMenu = function (options) {
    let elemObj = $(this);
    let off = false;

    function build() {
        //<editor-fold desc="Build Menu - UL">
        let pos = elemObj.position();
        let menu = $('<ul></ul>')
            .addClass(options.menuClass)
            .attr('id', 'btn_add_budget')
            .css('margin-left', pos.left + options.menuHorOffset)
            .css('margin-top', pos.top + options.menuVertOffset)
            .mouseenter(function () {
                off = false;
            })
            .mouseleave(function () {
                off = true;
                hide();
            });
        ;
        /* add attributes if they exist */
        console.log('LePopMenu.build().options.attr');
        console.log(options.attr);
        if (options.attr) {
            $.each(options.attr,function (a, v) {
                console.log('LePopMenu.build.options.attr.a = ' + a);
                console.log('LePopMenu.build.options.attr.v = ' + v);
                menu.attr(a, v);
            });
        }
        //</editor-fold>

        //<editor-fold desc="Build Menu Items - LI's">
        /** @var LePopMenuItemModel mi */
        $.each(options.menuItems, function (i, mi) {
            let menuItem = $('<li></li>')
                .html(mi.text)
                .click(mi.callBack)
            ;

            console.log('LePopMenu.build().mi.attr');
            console.log(mi.attr);
            if (mi.attr) {
                $.each(mi.attr,function (a, v) {
                    console.log('LePopMenu.build.mi.attr.a = ' + a);
                    console.log('LePopMenu.build.mi.attr.v = ' + v);
                    menuItem.attr(a, v);
                });
            }
            menu.append(menuItem)
        });
        //</editor-fold>

        // console.log('LePopMenu.build().options.elemTarget');
        // console.log(options.elemTarget);
        // console.log('LePopMenu.build().menu');
        // console.log(menu);
        elemObj.css('cursor', 'pointer');
        options.elemTarget.append(menu);
    }

    function hide() {
        /* When the mouse leaves the elemMenu, we want to make sure it did not re-enter the action button. */
        setTimeout(function () {
            if (off) {
                options.elemTarget.find('ul').hide();
            }
        }, 50);
    }

    /* If the clear flag is passed in, remove the existing menu */
    if (options.hasOwnProperty('clear') && true === options.clear) {
        options.elemTarget.find('ul').remove();
    }

    elemObj
        .mouseenter(function () {
            off = false;
            let menu = options.elemTarget.find('ul');
            if (menu.length) {
                menu.show();
            } else {
                build();
            }
        })
        .mouseleave(function () {
            off = true;
            hide();
        })
    ;
    return elemObj;
}

/**
 HOW-TO EXAMPLE

 let menuItemModel1 = new LePopMenuItemModel(
    'Menu Item 1',
     function () { // call back logic here; }
 )
 ;
 let menuItemModel2 = new LePopMenuItemModel(
     'Menu Item 2',
     function () { // call back logic here; }
 )
 ;
 let menuModel = new LePopMenuModel('Menu Name Here', [menuItemModel1, menuItemModel2], 25);
 $(DOMObj).LePopMenu(menuModel)
*/