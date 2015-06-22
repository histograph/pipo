
## DOCS

- specs for requires API fields etc
https://github.com/histograph/schemas/tree/master/io

## API workflow:

- POST /sources
- POST /sources/:source/pits
- POST /sources/:source/relations

- DELETE /sources means destroying all

Questions for Bert:

- DELETE did not mean ever reference to the carnaval set was removed. Especially relations were kept.



- Waarom levert zoeken op Teutengat niets op? (carnaval/241) (en zou moeten zijn carnaval/242)
- En zoeken op Toeterland wel?


 Voorzetje voor het uitleggen van de concepten
 
  "hg:sameHgConcept",
  
  
         "hg:containsHgConcept",
 		Used when one concept at a certain moment in time, contained another 
 		St Anthoniespoort en Waag, want het is er omheen/overheen gebouwd EN het vroegere ding maakt deel uit van het latere geheel...
 		
         "hg:withinHgConcept",
 		Inverse van bovenstaande
 		
         "hg:intersectsHgConcept",
 		merwedekanaal, keulse vaart, maar slechts een deel van het andere latere geheel
 		
         "hg:isUsedFor",
 		toponiemen, met begin en eindperiode
 		
         "hg:absorbed",
 		voor gemeentes die zijn samengevoegd
         "hg:absorbedBy",
 		inversed
 		
         "hg:originated",
         "hg:originatedFrom",
 		Dat een administratieve eenheid is ontstaan uit een andere 
 		
         "hg:contains",
         "hg:liesIn"
 		
