/*------------------------------------------------------------------------------

    Copyright (c) 2004 Media Development Loan Fund
 
    This file is part of the Campcaster project.
    http://campcaster.campware.org/
    To report bugs, send an e-mail to bugs@campware.org
 
    Campcaster is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
  
    Campcaster is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 
    You should have received a copy of the GNU General Public License
    along with Campcaster; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 
 
    Author   : $Author$
    Version  : $Revision$
    Location : $URL$

------------------------------------------------------------------------------*/
#ifndef TestWindow_h
#define TestWindow_h

#ifndef __cplusplus
#error This is a C++ include file
#endif


/* ============================================================ include files */

#ifdef HAVE_CONFIG_H
#include "configure.h"
#endif

#include <gtkmm.h>
#include <libglademm.h>

#include "LiveSupport/Core/LocalizedObject.h"
#include "LiveSupport/Core/Ptr.h"

#include "LiveSupport/Widgets/ComboBoxText.h"
#include "LiveSupport/Widgets/ZebraTreeView.h"
#include "LiveSupport/Widgets/ZebraTreeModelColumnRecord.h"


namespace LiveSupport {
namespace Widgets {

using namespace LiveSupport::Core;

/* ================================================================ constants */


/* =================================================================== macros */


/* =============================================================== data types */

/**
 *  A window, enabling interactive testing of UI components.
 *
 *  @author $Author$
 *  @version $Revision$
 */
class TestWindow : public LocalizedObject
{
    private:

        /**
         *  Configure the resource bundle.
         */
        void
        configureBundle (void)                                      throw ();

        /**
         *  Fill the tree model.
         */
        void
        fillTreeModel (void)                                        throw ();

        /**
         *  Set up the D'n'D callbacks.
         */
        void
        setupDndCallbacks (void)                                    throw ();


    protected:

        /**
         *  The window itself.
         */
        Gtk::Window *                   mainWindow;

        /**
         *  The combo box.
         */
        ComboBoxText *                  comboBox;

        /**
         *  The tree view.
         */
        ZebraTreeView *                 treeView;

        /**
         *  The drop target label.
         */
        Gtk::Label *                    label;

        /**
         *  The OK button.
         */
        Gtk::Button *                   okButton;

        /**
         *  The columns model needed by Gtk::TreeView.
         */
        class ModelColumns : public ZebraTreeModelColumnRecord
        {
            public:

                /**
                 *  A column showing a Pixbuf.
                 */
                Gtk::TreeModelColumn<Glib::RefPtr<Gdk::Pixbuf> >
                                                            pixbufColumn;

                /**
                 *  A text column.
                 */
                Gtk::TreeModelColumn<Glib::ustring>         textColumn;

                /**
                 *  Constructor.
                 */
                ModelColumns(void)                                  throw ()
                {
                    add(pixbufColumn);
                    add(textColumn);
                }
        };

        /**
         *  The column model.
         */
        ModelColumns                    modelColumns;

        /**
         *  The tree model, as a GTK reference.
         */
        Glib::RefPtr<Gtk::ListStore>    treeModel;

        /**
         *  Event handler for selection change in the combo box.
         */
        virtual void
        onComboBoxSelectionChanged (void)                           throw ();

        /**
         *  Event handler for the OK button being clicked.
         */
        virtual void
        onOkButtonClicked (void)                                    throw ();

        /**
         *  Event handler for the window being hidden.
         */
        virtual bool
        onDeleteEvent (GdkEventAny *     event)                     throw ();

        /**
         *  The callback for the start of the drag.
         */
        virtual void
        onTreeViewDragDataGet(
            const Glib::RefPtr<Gdk::DragContext> &      context,
            Gtk::SelectionData &                        selectionData,
            guint                                       info,
            guint                                       time)
                                                                    throw ();

        /**
         *  The callback for the end of the drag.
         */
        virtual void
        onTreeViewDragDataReceived(
            const Glib::RefPtr<Gdk::DragContext> &      context,
            int                                         x,
            int                                         y,
            const Gtk::SelectionData &                  selectionData,
            guint                                       info,
            guint                                       time)
                                                                    throw ();

        /**
         *  The callback for the end of the drag.
         */
        virtual void
        onLabelDragDataReceived(
            const Glib::RefPtr<Gdk::DragContext> &      context,
            int                                         x,
            int                                         y,
            const Gtk::SelectionData &                  selectionData,
            guint                                       info,
            guint                                       time)
                                                                    throw ();


    public:

        /**
         *  Constructor.
         */
        TestWindow (void)                                           throw ();

        /**
         *  Virtual destructor.
         */
        virtual
        ~TestWindow (void)                                          throw ()
        {
        }

        /**
         *  Run the window.
         */
        void
        run (void)                                                  throw ();
};

/* ================================================= external data structures */


/* ====================================================== function prototypes */


} // namespace Widgets
} // namespace LiveSupport

#endif // TestWindow_h

