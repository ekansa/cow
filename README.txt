
Query Service API for Easier Faceted search Queries on the NCS Repository



------LEGAL STUFF----------------------------------------------------


Copyright 2012 - Eric c. Kansa

--- copyright LICENSE -----
This software is licensed under the terms of the BSD software license
See here for specifics: http://www.opensource.org/licenses/bsd-license.php

This sofware uses the open source ZEND-PHP framework, and Zend's licensing requirements
apply to the Zend code (everything in the library/Zend directory).

----------------------------


THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.


-----------------------------------------------------------------------



WHAT DOES THIS SOFTWARE DO?



This software provides a proxy service for HTTP-GET requests to query 
the NCS repository.

The point of this code is to make it easier to query the NCS repository
for faceted searches. This code reads the XSD document in the NCS
repository, as well as its field lists, and uses this information to
formulae queries. Very little (hopefully nothing) is being hard coded,
so if the NCS's XSD changes (as metadata requirements in the repository
evolve), this will still work and allow for queries against the new
metadata schema.

This proxy service is easier to use than the standard NCS service because
it provides links that can be followed to further filter search results or
to remove such filters. The proxy service will also provide a paged-Atom
feed of all query results.

Development of this proxy service was informed by earlier developments on
the Open Context project (http://opencontext.org).

