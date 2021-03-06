<?php

/**
 * Tests for the SiteList class.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @since 1.21
 *
 * @ingroup Site
 * @ingroup Test
 *
 * @group Site
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class SiteListTest extends MediaWikiTestCase {

	/**
	 * Returns instances of SiteList implementing objects.
	 * @return array
	 */
	public function siteListProvider() {
		$sitesArrays = $this->siteArrayProvider();

		$listInstances = array();

		foreach ( $sitesArrays as $sitesArray ) {
			$listInstances[] = new SiteList( $sitesArray[0] );
		}

		return $this->arrayWrap( $listInstances );
	}

	/**
	 * Returns arrays with instances of Site implementing objects.
	 * @return array
	 */
	public function siteArrayProvider() {
		$sites = TestSites::getSites();

		$siteArrays = array();

		$siteArrays[] = $sites;

		$siteArrays[] = array( array_shift( $sites ) );

		$siteArrays[] = array( array_shift( $sites ), array_shift( $sites ) );

		return $this->arrayWrap( $siteArrays );
	}

	/**
	 * @dataProvider siteListProvider
	 * @param SiteList $sites
	 */
	public function testIsEmpty( SiteList $sites ) {
		$this->assertEquals( count( $sites ) === 0, $sites->isEmpty() );
	}

	/**
	 * @dataProvider siteListProvider
	 * @param SiteList $sites
	 */
	public function testGetSiteByGlobalId( SiteList $sites ) {
		if ( $sites->isEmpty() ) {
			$this->assertTrue( true );
		} else {
			/**
			 * @var Site $site
			 */
			foreach ( $sites as $site ) {
				$this->assertEquals( $site, $sites->getSite( $site->getGlobalId() ) );
			}
		}
	}

	/**
	 * @dataProvider siteListProvider
	 * @param SiteList $sites
	 */
	public function testGetSiteByInternalId( $sites ) {
		/**
		 * @var Site $site
		 */
		foreach ( $sites as $site ) {
			if ( is_integer( $site->getInternalId() ) ) {
				$this->assertEquals( $site, $sites->getSiteByInternalId( $site->getInternalId() ) );
			}
		}

		$this->assertTrue( true );
	}

	/**
	 * @dataProvider siteListProvider
	 * @param SiteList $sites
	 */
	public function testHasGlobalId( $sites ) {
		$this->assertFalse( $sites->hasSite( 'non-existing-global-id' ) );
		$this->assertFalse( $sites->hasInternalId( 720101010 ) );

		if ( !$sites->isEmpty() ) {
			/**
			 * @var Site $site
			 */
			foreach ( $sites as $site ) {
				$this->assertTrue( $sites->hasSite( $site->getGlobalId() ) );
			}
		}
	}

	/**
	 * @dataProvider siteListProvider
	 * @param SiteList $sites
	 */
	public function testHasInternallId( $sites ) {
		/**
		 * @var Site $site
		 */
		foreach ( $sites as $site ) {
			if ( is_integer( $site->getInternalId() ) ) {
				$this->assertTrue( $site, $sites->hasInternalId( $site->getInternalId() ) );
			}
		}

		$this->assertFalse( $sites->hasInternalId( -1 ) );
	}

	/**
	 * @dataProvider siteListProvider
	 * @param SiteList $sites
	 */
	public function testGetGlobalIdentifiers( SiteList $sites ) {
		$identifiers = $sites->getGlobalIdentifiers();

		$this->assertTrue( is_array( $identifiers ) );

		$expected = array();

		/**
		 * @var Site $site
		 */
		foreach ( $sites as $site ) {
			$expected[] = $site->getGlobalId();
		}

		$this->assertArrayEquals( $expected, $identifiers );
	}

	/**
	 * @dataProvider siteListProvider
	 *
	 * @since 1.21
	 *
	 * @param SiteList $list
	 */
	public function testSerialization( SiteList $list ) {
		$serialization = serialize( $list );
		/**
		 * @var SiteArray $copy
		 */
		$copy = unserialize( $serialization );

		$this->assertArrayEquals( $list->getGlobalIdentifiers(), $copy->getGlobalIdentifiers() );

		/**
		 * @var Site $site
		 */
		foreach ( $list as $site ) {
			$this->assertTrue( $copy->hasInternalId( $site->getInternalId() ) );
		}
	}

}
