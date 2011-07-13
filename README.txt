LICENCE
-------
Copyright 2011 AJweb.eu. Some parts (ideas mainly) Copyright 2005 ZhenIT 

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

INSTALL
-------
1. Copy all the files to their corresponding folders in your Zen Cart installation
2. Go to the Admin site > Modules > Payment
3. Click on cyberpack module and then on "Install"
4. Fill the necessary details (these will be given to you by your bank)
5. Test


TODO
----
* Next version will allow some more options on admin interface (type of transaction among them)
* Tests are saved on the cyberpack side undistinguished from real transactions, so when if you use different sites/dbs for tests and production  (as it would be recommended) the IDs of the transactions made on production will be the same as the ones already saved on cyberpack side. To avoid this (although I think it is a problem on the cyberpack side, not mine), I plan to give the option start Ids from a given integer


CONTRIBUTE
----------
Please notify any improvement or problem you find with the software. I can't give free support but I will try to solve any problem that arises for the benefit of all the users of this software. Please write to "info@ajweb.eu". Thanks.