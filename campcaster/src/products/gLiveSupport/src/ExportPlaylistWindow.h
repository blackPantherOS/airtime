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
 
 
    Author   : $Author: fgerlits $
    Version  : $Revision$
    Location : $URL: svn+ssh://fgerlits@code.campware.org/home/svn/repo/livesupport/trunk/livesupport/src/products/gLiveSupport/src/ExportPlaylistWindow.h $

------------------------------------------------------------------------------*/
#ifndef ExportPlaylistWindow_h
#define ExportPlaylistWindow_h

#ifndef __cplusplus
#error This is a C++ include file
#endif


/* ============================================================ include files */

#ifdef HAVE_CONFIG_H
#include "configure.h"
#endif

#include <gtkmm.h>
#include <libglademm.h>

#include "LiveSupport/Core/Playlist.h"
#include "LiveSupport/Core/LocalizedObject.h"
#include "ExportFormatRadioButtons.h"
#include "GLiveSupport.h"


namespace LiveSupport {
namespace GLiveSupport {

using namespace LiveSupport::Core;

/* ================================================================ constants */


/* =================================================================== macros */


/* =============================================================== data types */

/**
 *  The Export Playlist window.  This is a pop-up window accessible from the
 *  right-click menus of the Scratchpad, Live Mode and Search/Browse windows.
 *  It lets the user select the format of the exported playlist, and the 
 *  location where it will be saved.
 *
 *  @author $Author: fgerlits $
 *  @version $Revision$
 */
class ExportPlaylistWindow : public LocalizedObject
{
    private:

        /**
         *  The GLiveSupport object, holding the state of the application.
         */
        Ptr<GLiveSupport>::Ref                  gLiveSupport;

        /**
         *  The Glade object, containing the visual design.
         */
        Glib::RefPtr<Gnome::Glade::Xml>         glade;

        /**
         *  The main window for this class.
         */
        Gtk::Window *                           mainWindow;

        /**
         *  The playlist to be exported.
         */
        Ptr<Playlist>::Ref                      playlist;
        
        /**
         *  The playlist to be exported.
         */
        Ptr<const Glib::ustring>::Ref           token;
        
        /**
         *  The radio buttons for selecting the export format.
         */
        Ptr<ExportFormatRadioButtons>::Ref      formatButtons;
        
        /**
         *  Cancel the current operation.
         *  Call exportPlaylistClose() on token, and reset it to 0.
         */
        void
        resetToken(void)                                            throw ();


    protected:

        /**
         *  Event handler for the Cancel button being clicked.
         */
        void
        onCancelButtonClicked(void)                                 throw ();

        /**
         *  Event handler for the Save button being clicked.
         */
        void
        onSaveButtonClicked(void)                                   throw ();

        /**
         *  Event handler called when the the window gets hidden.
         *
         *  It closes the exporting operations, if there is one in progress.
         */
        virtual bool
        onDeleteEvent(GdkEventAny *     event)                      throw ();


    public:

        /**
         *  Constructor.
         *
         *  @param  gLiveSupport    the gLiveSupport object, containing
         *                          all the vital info.
         *  @param  gladeDir        the directory where the Glade files are.
         *  @param  playlist        the playlist to be exported.
         */
        ExportPlaylistWindow(Ptr<GLiveSupport>::Ref             gLiveSupport,
                             const Glib::ustring &              gladeDir,
                             Ptr<Playlist>::Ref                 playlist)
                                                                    throw ();

        /**
         *  Virtual destructor.
         */
        virtual
        ~ExportPlaylistWindow(void)                                 throw ()
        {
        }

        /**
         *  Get the underlying Gtk::Window.
         */
        virtual Gtk::Window *
        getWindow(void)                                             throw ()
        {
            return mainWindow;
        }
};

/* ================================================= external data structures */


/* ====================================================== function prototypes */


} // namespace GLiveSupport
} // namespace LiveSupport

#endif // ExportPlaylistWindow_h

