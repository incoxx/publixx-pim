import { useState } from 'react';
import { Button } from './components/ui/button';
import { Badge } from './ui/badge';
import { ChevronRight, Search, BookOpen, Download, Languages, CheckCircle } from 'lucide-react';
import erbeLogo from 'figma:asset/d4b2bbfaf539cf2dc5107501948de59a842c8dff.png';
import medicalImage from 'figma:asset/08de4e571706d339586554292c881d2e38093103.png';

const countries = [
  { code: 'usa', name: 'United States', flag: '🇺🇸', region: 'Americas' },
  { code: 'canada', name: 'Canada', flag: '🇨🇦', region: 'Americas' },
  { code: 'uk', name: 'United Kingdom', flag: '🇬🇧', region: 'Europe' },
  { code: 'germany', name: 'Germany', flag: '🇩🇪', region: 'Europe' },
  { code: 'france', name: 'France', flag: '🇫🇷', region: 'Europe' },
  { code: 'spain', name: 'Spain', flag: '🇪🇸', region: 'Europe' },
  { code: 'italy', name: 'Italy', flag: '🇮🇹', region: 'Europe' },
  { code: 'japan', name: 'Japan', flag: '🇯🇵', region: 'Asia Pacific' },
  { code: 'australia', name: 'Australia', flag: '🇦🇺', region: 'Asia Pacific' },
];

export default function App() {
  const [selectedCountry, setSelectedCountry] = useState<string | null>(null);
  const [searchTerm, setSearchTerm] = useState('');

  const filteredCountries = countries.filter(country =>
    country.name.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const groupedCountries = filteredCountries.reduce((acc, country) => {
    if (!acc[country.region]) {
      acc[country.region] = [];
    }
    acc[country.region].push(country);
    return acc;
  }, {} as Record<string, typeof countries>);

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-white flex items-center justify-center p-4">
      <div className="w-full max-w-6xl">
        <div className="bg-white rounded-lg overflow-hidden border border-blue-200 shadow-lg">
          {/* Split Layout Header */}
          <div className="grid md:grid-cols-2">
            {/* Left Side - Branding */}
            <div className="bg-white p-8 md:p-12 flex flex-col justify-between border-r border-blue-200">
              <div>
                <div className="flex justify-end mb-8">
                  <img src={erbeLogo} alt="Erbe" className="h-8" />
                </div>
                <p className="text-[#00AEEF] mb-3">Electronic instructions for use</p>
                <h1 className="text-[#004D6D] mb-3 text-3xl">Access Your Product Documentation</h1>
                <p className="text-[#546E7A] mb-6">
                  Access comprehensive product documentation, safety information, and usage instructions instantly.
                </p>
                <div className="space-y-3">
                  <div className="flex items-center gap-3 text-[#004D6D]">
                    <div className="w-5 h-5 rounded-full bg-[#00AEEF] flex items-center justify-center">
                      <CheckCircle className="w-4 h-4 text-white" />
                    </div>
                    <span>Instant digital access</span>
                  </div>
                  <div className="flex items-center gap-3 text-[#004D6D]">
                    <div className="w-5 h-5 rounded-full bg-[#00AEEF] flex items-center justify-center">
                      <CheckCircle className="w-4 h-4 text-white" />
                    </div>
                    <span>Multiple languages available</span>
                  </div>
                  <div className="flex items-center gap-3 text-[#004D6D]">
                    <div className="w-5 h-5 rounded-full bg-[#00AEEF] flex items-center justify-center">
                      <CheckCircle className="w-4 h-4 text-white" />
                    </div>
                    <span>Always up-to-date content</span>
                  </div>
                </div>
              </div>
              <div className="hidden md:block mt-8">
                <img 
                  src={medicalImage} 
                  alt="Medical Equipment"
                  className="rounded-lg border-2 border-blue-100"
                />
              </div>
            </div>

            {/* Right Side - Interactive Selection */}
            <div className="p-8 md:p-12 flex flex-col">
              <div className="flex-1">
                <div className="mb-6">
                  <h3 className="text-[#004D6D] mb-2">Select Your Region</h3>
                  <p className="text-[#546E7A]">
                    Choose your country to view instructions in your language
                  </p>
                </div>

                {/* Search */}
                <div className="relative mb-6">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-[#00AEEF]" />
                  <input
                    type="text"
                    placeholder="Search countries..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="w-full pl-10 pr-4 py-3 border border-blue-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#00AEEF] focus:border-transparent bg-blue-50/50"
                  />
                </div>

                {/* Country List */}
                <div className="space-y-4 mb-6 overflow-y-auto max-h-96">
                  {Object.entries(groupedCountries).map(([region, countries]) => (
                    <div key={region}>
                      <div className="text-xs text-[#00AEEF] mb-2 px-2">{region}</div>
                      <div className="space-y-2">
                        {countries.map((country) => (
                          <button
                            key={country.code}
                            onClick={() => setSelectedCountry(country.code)}
                            className={`w-full p-4 rounded-lg border-2 transition-all text-left flex items-center justify-between group hover:border-[#00AEEF] ${
                              selectedCountry === country.code
                                ? 'border-[#00AEEF] bg-blue-50'
                                : 'border-blue-200 hover:bg-blue-50/50'
                            }`}
                          >
                            <div className="flex items-center gap-3">
                              <span className="text-2xl">{country.flag}</span>
                              <span className={`${
                                selectedCountry === country.code ? 'text-[#004D6D]' : 'text-[#1a1a1a]'
                              }`}>
                                {country.name}
                              </span>
                            </div>
                            {selectedCountry === country.code ? (
                              <CheckCircle className="w-5 h-5 text-[#00AEEF]" />
                            ) : (
                              <ChevronRight className="w-5 h-5 text-[#00AEEF] opacity-40 group-hover:opacity-100 transition-opacity" />
                            )}
                          </button>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Action Button */}
              <Button 
                className="w-full h-12 bg-[#00AEEF] hover:bg-[#0097D6] text-white"
                disabled={!selectedCountry}
                size="lg"
              >
                {selectedCountry ? 'View Instructions' : 'Select a country to continue'}
                {selectedCountry && <ChevronRight className="w-5 h-5 ml-2" />}
              </Button>

              {/* Quick Links */}
              <div className="flex gap-4 mt-6 justify-center">
                <Button variant="ghost" size="sm" className="text-[#00AEEF] hover:text-[#0097D6] hover:bg-blue-50">
                  <BookOpen className="w-4 h-4 mr-2" />
                  About eifu
                </Button>
                <Button variant="ghost" size="sm" className="text-[#00AEEF] hover:text-[#0097D6] hover:bg-blue-50">
                  <Download className="w-4 h-4 mr-2" />
                  Download PDF
                </Button>
              </div>
            </div>
          </div>

          {/* Features Bar */}
          <div className="bg-gradient-to-r from-blue-50 to-white border-t border-blue-200 p-6">
            <div className="max-w-4xl mx-auto flex flex-wrap justify-center gap-8 text-center">
              <div className="flex items-center gap-2 text-[#004D6D]">
                <Languages className="w-5 h-5 text-[#00AEEF]" />
                <span className="text-sm">25+ Languages</span>
              </div>
              <div className="flex items-center gap-2 text-[#004D6D]">
                <Download className="w-5 h-5 text-[#00AEEF]" />
                <span className="text-sm">PDF Download</span>
              </div>
              <div className="flex items-center gap-2 text-[#004D6D]">
                <BookOpen className="w-5 h-5 text-[#00AEEF]" />
                <span className="text-sm">Video Tutorials</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}