/*------------------------------------------------------------------------------

    Copyright (c) 2004 Media Development Loan Fund
 
    This file is part of the LiveSupport project.
    http://livesupport.campware.org/
    To report bugs, send an e-mail to bugs@campware.org
 
    LiveSupport is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
  
    LiveSupport is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 
    You should have received a copy of the GNU General Public License
    along with LiveSupport; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 
 
    Author   : $Author$
    Version  : $Revision$
    Location : $URL$

------------------------------------------------------------------------------*/

/* ============================================================ include files */

#ifdef HAVE_CONFIG_H
#include "configure.h"
#endif

#if HAVE_UNISTD_H
#include <unistd.h>
#else
#error "Need unistd.h"
#endif


#include <string>
#include <iostream>
#include <XmlRpcValue.h>

#include "LiveSupport/Db/ConnectionManagerFactory.h"
#include "LiveSupport/StorageClient/StorageClientFactory.h"
#include "LiveSupport/Authentication/AuthenticationClientFactory.h"
#include "LiveSupport/Core/XmlRpcTools.h"

#include "SchedulerDaemon.h"
#include "OpenPlaylistForEditingMethod.h"
#include "AddAudioClipToPlaylistMethod.h"
#include "UpdateFadeInFadeOutMethod.h"

#include "UpdateFadeInFadeOutMethodTest.h"

using namespace std;
using namespace LiveSupport::Db;
using namespace LiveSupport::StorageClient;
using namespace LiveSupport::Scheduler;
using namespace LiveSupport::Authentication;


/* ===================================================  local data structures */


/* ================================================  local constants & macros */

CPPUNIT_TEST_SUITE_REGISTRATION(UpdateFadeInFadeOutMethodTest);


/* ===============================================  local function prototypes */


/* =============================================================  module code */

/*------------------------------------------------------------------------------
 *  Set up the test environment
 *----------------------------------------------------------------------------*/
void
UpdateFadeInFadeOutMethodTest :: setUp(void)                         throw ()
{
    Ptr<SchedulerDaemon>::Ref   scheduler = SchedulerDaemon::getInstance();
    try {
        Ptr<StorageClientInterface>::Ref    storage = scheduler->getStorage();
        storage->reset();

    } catch (std::invalid_argument &e) {
        CPPUNIT_FAIL("semantic error in configuration file");
    } catch (xmlpp::exception &e) {
        CPPUNIT_FAIL("error parsing configuration file");
    } catch (std::exception &e) {
        CPPUNIT_FAIL(e.what());
    }
    
    authentication = scheduler->getAuthentication();
    try {
        sessionId = authentication->login("root", "q");
    } catch (XmlRpcException &e) {
        std::string eMsg = "could not log in:\n";
        eMsg += e.what();
        CPPUNIT_FAIL(eMsg);
    }
}


/*------------------------------------------------------------------------------
 *  Clean up the test environment
 *----------------------------------------------------------------------------*/
void
UpdateFadeInFadeOutMethodTest :: tearDown(void)                      throw ()
{
    authentication->logout(sessionId);
    sessionId.reset();
    authentication.reset();
}


/*------------------------------------------------------------------------------
 *  Just a very simple smoke test
 *----------------------------------------------------------------------------*/
void
UpdateFadeInFadeOutMethodTest :: firstTest(void)
                                                throw (CPPUNIT_NS::Exception)
{
    Ptr<OpenPlaylistForEditingMethod>::Ref 
               openPlaylistMethod(new OpenPlaylistForEditingMethod());
    Ptr<UpdateFadeInFadeOutMethod>::Ref 
               updateFadeMethod(new UpdateFadeInFadeOutMethod());
    XmlRpc::XmlRpcValue             parameters;
    XmlRpc::XmlRpcValue             rootParameter;
    rootParameter.setSize(1);
    XmlRpc::XmlRpcValue             result;

    parameters["sessionId"]         = sessionId->getId();
    parameters["playlistId"]        = "0000000000000001";
    parameters["playlistElementId"] = "0000000000009999";
    parameters["fadeIn"]            = 0;
    rootParameter[0]                = parameters;

    result.clear();
    try {
        updateFadeMethod->execute(rootParameter, result);
        CPPUNIT_FAIL("did not notice missing fade out parameter");
    } catch (XmlRpc::XmlRpcException &e) {
        CPPUNIT_ASSERT(e.getCode() == 1605);    // missing fade out
    }

    parameters["fadeOut"]        = 2100;
    rootParameter[0]             = parameters;

    result.clear();
    try {
        updateFadeMethod->execute(rootParameter, result);
        CPPUNIT_FAIL("allowed update fade info without opening playlist first");
    } catch (XmlRpc::XmlRpcException &e) {
        CPPUNIT_ASSERT(e.getCode() == 1607);    // not open for editing
    }

    result.clear();
    try {
        openPlaylistMethod->execute(rootParameter, result);
    } catch (XmlRpc::XmlRpcException &e) {
        std::stringstream eMsg;
        eMsg << "XML-RPC method returned error: " << e.getCode()
             << " - " << e.getMessage();
        CPPUNIT_FAIL(eMsg.str());
    }

    result.clear();
    try {
        updateFadeMethod->execute(rootParameter, result);
        CPPUNIT_FAIL("allowed update fade info for non-existent "
                     "playlist element");
    } catch (XmlRpc::XmlRpcException &e) {
        CPPUNIT_ASSERT(e.getCode() == 1608);// no such playlist element
    }

    parameters["playlistElementId"] = "0000000000000101";
    rootParameter[0]                = parameters;

    result.clear();
    try {
        updateFadeMethod->execute(rootParameter, result);
    } catch (XmlRpc::XmlRpcException &e) {
        std::stringstream eMsg;
        eMsg << "XML-RPC method returned error: " << e.getCode()
             << " - " << e.getMessage();
        CPPUNIT_FAIL(eMsg.str());
    }
}
