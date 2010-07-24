/*------------------------------------------------------------------------------

    Copyright (c) 2010 Sourcefabric O.P.S.
 
    This file is part of the Campcaster project.
    http://campcaster.sourcefabric.org/
 
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
 
------------------------------------------------------------------------------*/
#ifndef BrowseEntry_h
#define BrowseEntry_h

#ifndef __cplusplus
#error This is a C++ include file
#endif


/* ============================================================ include files */

#ifdef HAVE_CONFIG_H
#include "configure.h"
#endif

#include "LiveSupport/Core/Ptr.h"
#include "LiveSupport/Core/SearchCriteria.h"
#include "BrowseItem.h"
#include "GLiveSupport.h"

#include "GuiComponent.h"


namespace LiveSupport {
namespace GLiveSupport {

using namespace LiveSupport::Core;
    
/* ================================================================ constants */


/* =================================================================== macros */


/* =============================================================== data types */

/**
 *  A Gtk::HBox with one or more search input fields in it.
 *
 */
class BrowseEntry : public GuiComponent
{
    private:
    
        /**
         *  Default constructor.
         */
        BrowseEntry(void)                               throw ();

        /**
         *  The first BrowseItem entry field.
         */
        Ptr<BrowseItem>::Ref    browseItemOne;

        /**
         *  The second BrowseItem entry field.
         */
        Ptr<BrowseItem>::Ref    browseItemTwo;

        /**
         *  The third BrowseItem entry field.
         */
        Ptr<BrowseItem>::Ref    browseItemThree;


    public:
    
        /**
         *  Constructor with localization parameter.
         *
         *  @param  parent  the GuiObject which contains this one.
         */
        BrowseEntry(GuiObject *         parent)
                                                                throw ();

        /**
         *  A virtual destructor.
         */
        virtual
        ~BrowseEntry(void)                                      throw ()
        {
        }

        /**
         *  Return the current state of the search fields.
         *
         *  @return a new LiveSupport::StorageClient::SearchCriteria instance,
         *          which contains the data entered by the user
         */
        Ptr<SearchCriteria>::Ref
        getSearchCriteria(void)                                 throw ()
        {
            return browseItemThree->getSearchCriteria();
        }


        /**
         *  The signal raised when either the combo box or the tree view
         *  selection has changed.
         *
         *  @return the signalChanged() of the last browse item
         */
        sigc::signal<void>
        signalChanged(void)                                     throw ()
        {
            return browseItemThree->signalChanged();
        }
};


/* ================================================= external data structures */


/* ====================================================== function prototypes */


} // namespace GLiveSupport
} // namespace LiveSupport

#endif // BrowseEntry_h

